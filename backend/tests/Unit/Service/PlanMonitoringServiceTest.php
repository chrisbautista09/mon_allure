<?php

namespace App\Tests\Unit\Service;

use App\Entity\AlgorithmParameter;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\AlgorithmParameterRepository;
use App\Repository\TrainingPlanRepository;
use App\Service\AlgorithmParameterService;
use App\Service\PlanMonitoringService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PlanMonitoringServiceTest extends TestCase
{
    public function testAnalyzePlanCalculatesEveryMonitoringIndicator(): void
    {
        $analysis = $this->service()->analyzePlan($this->problematicPlan());

        self::assertSame('Plan à surveiller', $analysis['name']);
        self::assertSame('runner@example.com', $analysis['user']['email']);
        self::assertSame('IMPOSSIBLE', $analysis['feasibilityIndicator']);
        self::assertSame(20.0, $analysis['progressScore']);
        self::assertSame([
            'type' => 'distance',
            'value' => 10.0,
            'unit' => 'km',
            'terrainType' => 'road',
        ], $analysis['objective']);
        self::assertSame(3, $analysis['trainingLoad']['sessionCount']);
        self::assertSame(150, $analysis['trainingLoad']['plannedDurationMinutes']);
        self::assertSame(30.0, $analysis['trainingLoad']['plannedDistanceKm']);
        self::assertSame(3, $analysis['trainingLoad']['maximumSessionsPerWeek']);
        self::assertSame(50.0, $analysis['successRate']);
        self::assertSame(1, $analysis['completedSessions']);
        self::assertSame(2, $analysis['terminalSessions']);
        self::assertTrue($analysis['isProblematic']);
        self::assertSame(5, $analysis['anomalyCount']);
        self::assertCount(5, $analysis['monitoringHistory']);
        self::assertFalse($analysis['monitoringHistory'][0]['resolved']);
        self::assertSame([
            'IMPOSSIBLE_FEASIBILITY',
            'LOW_PROGRESS',
            'INCONSISTENT_DURATION',
            'EXCESSIVE_TRAINING_LOAD',
            'LOW_SUCCESS_RATE',
        ], $analysis['anomalies']);
    }

    public function testHealthyPlanHasNoAnomaly(): void
    {
        $plan = $this->plan('intermediate', 'OPTIMAL', 85, 2, 8);
        $plan->addSession($this->session(1, 'completed'));
        $plan->addSession($this->session(1, 'completed'));
        $plan->addSession($this->session(1, 'completed'));

        self::assertSame([], $this->service()->detectAnomalies($plan));
    }

    public function testExactThresholdsDoNotTriggerFalsePositive(): void
    {
        $plan = $this->plan('performance', 'BON', 30, 8, 8);
        for ($index = 0; $index < 4; ++$index) {
            $plan->addSession($this->session(1, 'completed'));
        }
        $plan->addSession($this->session(1, 'missed'));

        self::assertSame([], $this->service()->detectAnomalies($plan));
    }

    public function testWeakRealFeasibilityValueIsDetectedAsImpossible(): void
    {
        $plan = $this->plan('discovery', 'FAIBLE', 80, 1, 8);

        self::assertSame(
            [PlanMonitoringService::ANOMALY_IMPOSSIBLE_FEASIBILITY],
            $this->service()->detectAnomalies($plan),
        );
    }

    public function testOverviewReturnsAnalyzedPlansAndPaginationMetadata(): void
    {
        $plan = $this->plan('discovery', 'BON', 75, 1, 8);
        $paginator = $this->createStub(Paginator::class);
        $paginator->method('getIterator')->willReturn(new \ArrayIterator([$plan]));
        $paginator->method('count')->willReturn(21);
        $repository = $this->createMock(TrainingPlanRepository::class);
        $repository->expects(self::once())
            ->method('searchForAdmin')
            ->with('ALL', '', 'ALL', 2, 20)
            ->willReturn($paginator);

        $overview = $this->service($repository)->getPlansOverview(2, 20);

        self::assertCount(1, $overview['plans']);
        self::assertSame([
            'page' => 2,
            'perPage' => 20,
            'total' => 21,
            'pages' => 2,
        ], $overview['pagination']);
    }

    private function service(?TrainingPlanRepository $trainingPlanRepository = null): PlanMonitoringService
    {
        $parameterRepository = $this->createStub(AlgorithmParameterRepository::class);
        $parameterRepository->method('findCurrent')->willReturn($this->parameters());

        return new PlanMonitoringService(
            $trainingPlanRepository ?? $this->createStub(TrainingPlanRepository::class),
            new AlgorithmParameterService($parameterRepository),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    /** @return array<string, AlgorithmParameter> */
    private function parameters(): array
    {
        $values = [
            AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY => 2,
            AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE => 3,
            AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE => 5,
            AlgorithmParameter::KEY_SUCCESS_VALIDATION_RATE => 80,
        ];
        $parameters = [];
        foreach ($values as $key => $value) {
            $parameters[$key] = (new AlgorithmParameter())
                ->setParameterKey($key)
                ->setParameterValue($value);
        }

        return $parameters;
    }

    private function problematicPlan(): TrainingPlan
    {
        $plan = $this->plan('discovery', 'IMPOSSIBLE', 20, 9, 8);
        $plan->addSession($this->session(1, 'completed'));
        $plan->addSession($this->session(1, 'missed'));
        $plan->addSession($this->session(1, 'planned'));

        return $plan;
    }

    private function plan(
        string $pole,
        string $feasibility,
        float $progress,
        int $currentWeek,
        int $durationWeeks,
    ): TrainingPlan {
        $user = (new User())
            ->setEmail('runner@example.com')
            ->setPseudo('runner')
            ->setPassword('test-password');

        return (new TrainingPlan())
            ->setName($feasibility === 'IMPOSSIBLE' ? 'Plan à surveiller' : 'Plan sain')
            ->setPoleType($pole)
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator($feasibility)
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-09-26'))
            ->setDurationWeeks($durationWeeks)
            ->setCurrentWeek($currentWeek)
            ->setProgressScore($progress)
            ->setUser($user);
    }

    private function session(int $week, string $status): Session
    {
        return (new Session())
            ->setWeekIndex($week)
            ->setDayOfWeek(1)
            ->setTitle('Séance')
            ->setSessionType('endurance')
            ->setPlannedDurationMin(50)
            ->setPlannedDistanceKm(10)
            ->setDate(new \DateTimeImmutable('2026-08-01'))
            ->setStatus($status);
    }
}
