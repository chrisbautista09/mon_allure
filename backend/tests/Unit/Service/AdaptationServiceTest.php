<?php

namespace App\Tests\Unit\Service;

use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\AdaptationDecision;
use App\Repository\AlgorithmParameterRepository;
use App\Repository\IntensityZoneRepository;
use App\Repository\PerformanceRepository;
use App\Repository\SessionRepository;
use App\Service\AdaptationService;
use App\Service\PerformanceEvaluationService;
use App\Service\ProgressScoreCalculatorService;
use App\Service\SessionGeneratorService;
use PHPUnit\Framework\TestCase;

final class AdaptationServiceTest extends TestCase
{
    public function testSuperiorPerformanceIncreasesProgressAndRecommendsHigherLoad(): void
    {
        $performance = $this->performance(10.5, 3600, 130, 50);

        $result = $this->service()->adapt($performance);

        self::assertSame(AdaptationDecision::INCREASE, $result->decision);
        self::assertSame(1.05, $result->loadFactor);
        self::assertSame(50.0, $result->previousProgressScore);
        self::assertSame(100.0, $result->progressScore);
        self::assertSame(100.0, $performance->getSession()?->getTrainingPlan()?->getProgressScore());
    }

    public function testMatchingPerformanceMaintainsLoadAndRewardsCompletion(): void
    {
        $result = $this->service()->adapt($this->performance(10, 3600, 130, 50));

        self::assertSame(AdaptationDecision::MAINTAIN, $result->decision);
        self::assertSame(1.0, $result->loadFactor);
        self::assertSame(100.0, $result->progressScore);
    }

    public function testInsufficientPerformanceReducesProgressAndRecommendedLoad(): void
    {
        $result = $this->service()->adapt($this->performance(8.5, 3600, 130, 50));

        self::assertSame(AdaptationDecision::REDUCE, $result->decision);
        self::assertSame(0.95, $result->loadFactor);
        self::assertSame(91.0, $result->progressScore);
    }

    public function testProgressScoreCalculationIsIndependentFromPreviousStoredScore(): void
    {
        $upper = $this->service()->adapt($this->performance(10.5, 3600, 130, 99));
        $samePerformance = $this->service()->adapt($this->performance(10.5, 3600, 130, 2));

        self::assertSame(100.0, $upper->progressScore);
        self::assertSame($upper->progressScore, $samePerformance->progressScore);
    }

    public function testPerformanceMustBelongToTrainingPlan(): void
    {
        $performance = (new Performance())
            ->setSession(new Session())
            ->setDistanceKm(10)
            ->setDurationSec(3600);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->adapt($performance);
    }

    public function testCompleteThreeWeekBlockTriggersFutureSessionRecalibration(): void
    {
        $performance = $this->performance(10.5, 3600, 130, 50);
        $currentSession = $performance->getSession();
        $plan = $currentSession?->getTrainingPlan();
        self::assertNotNull($currentSession);
        self::assertNotNull($plan);
        $currentSession->setWeekIndex(3)->setStatus('completed');
        $firstSession = (new Session())
            ->setWeekIndex(1)
            ->setStatus('completed')
            ->setDate(new \DateTimeImmutable('2026-08-18'));
        $plan->addSession($firstSession);
        $secondSession = (new Session())
            ->setWeekIndex(2)
            ->setStatus('missed')
            ->setDate(new \DateTimeImmutable('2026-08-25'));
        $plan->addSession($secondSession);
        $futureSession = (new Session())
            ->setWeekIndex(4)
            ->setStatus('planned')
            ->setDate(new \DateTimeImmutable('2026-09-08'));
        $plan->addSession($futureSession);
        $sessionGenerator = $this->createMock(SessionGeneratorService::class);
        $sessionGenerator
            ->expects(self::once())
            ->method('regenerateUpcoming')
            ->with($plan, $currentSession, 1.05)
            ->willReturn([$futureSession]);

        $result = $this->service(
            $sessionGenerator,
            [$firstSession, $secondSession, $currentSession],
            60,
            5,
        )->adapt($performance);

        self::assertSame(1, $result->adjustedSessionsCount);
        self::assertSame(66.67, $result->successRate);
        self::assertSame(60.0, $result->successValidationRate);
        self::assertCount(1, $plan->getAdaptationHistory());
        $history = $plan->getAdaptationHistory()[0];
        self::assertSame(AdaptationDecision::INCREASE->value, $history['decision']);
        self::assertStringContainsString('66.67', $history['reason']);
        self::assertStringContainsString('5.00 %', $history['modification']);
    }

    public function testSuccessRateUsesOnlyPastSessionsWithinPeriod(): void
    {
        $sessions = [
            $this->session('2026-08-02', 'completed'),
            $this->session('2026-08-04', 'completed'),
            $this->session('2026-08-06', 'missed'),
            $this->session('2026-07-30', 'completed'),
            $this->session('2026-08-11', 'completed'),
            $this->session('2026-08-12', 'planned'),
        ];

        $rate = $this->service()->calculateSuccessRate(
            $sessions,
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-31'),
            new \DateTimeImmutable('2026-08-11'),
        );

        self::assertSame(66.67, $rate);
    }

    public function testSuccessRateIsZeroWhenPeriodHasNoPastSession(): void
    {
        $rate = $this->service()->calculateSuccessRate(
            [$this->session('2026-08-12', 'completed')],
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-31'),
            new \DateTimeImmutable('2026-08-11'),
        );

        self::assertSame(0.0, $rate);
    }

    public function testSuccessRateRejectsAnInvalidPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->calculateSuccessRate(
            [],
            new \DateTimeImmutable('2026-08-31'),
            new \DateTimeImmutable('2026-08-01'),
        );
    }

    public function testSuccessValidationRateIsReadDynamicallyFromAlgorithmParameter(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('success_validation_rate')
            ->setParameterValue(80);

        self::assertTrue($this->service()->meetsSuccessValidationRate(80, $parameter));

        $parameter->updateValue(85);

        self::assertFalse($this->service()->meetsSuccessValidationRate(80, $parameter));
    }

    public function testSuccessValidationRejectsAnUnrelatedAlgorithmParameter(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('progression_max_percent')
            ->setParameterValue(5);

        $this->expectException(\LogicException::class);
        $this->service()->meetsSuccessValidationRate(80, $parameter);
    }

    public function testSuccessValidationRejectsAnInvalidSuccessRate(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('success_validation_rate')
            ->setParameterValue(80);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->meetsSuccessValidationRate(101, $parameter);
    }

    public function testProgressionMaxPercentRejectsAnUnsafeValue(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('progression_max_percent')
            ->setParameterValue(10.01);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('0 et 10');
        $parameter->getProgressionMaxPercent();
    }

    public function testFailedBlockMaintainsLoadWhenRecoveryWeekIsNotDue(): void
    {
        $performance = $this->performance(10.5, 3600, 130, 50);
        $currentSession = $performance->getSession();
        $plan = $currentSession?->getTrainingPlan();
        self::assertNotNull($currentSession);
        self::assertNotNull($plan);
        $currentSession
            ->setWeekIndex(6)
            ->setDate(new \DateTimeImmutable('2026-09-22'))
            ->setStatus('completed');
        $firstSession = $this->session('2026-09-08', 'completed')->setWeekIndex(4);
        $secondSession = $this->session('2026-09-15', 'missed')->setWeekIndex(5);
        $plan->addSession($firstSession)->addSession($secondSession);
        $sessionGenerator = $this->createMock(SessionGeneratorService::class);
        $sessionGenerator->expects(self::never())->method('regenerateUpcoming');

        $result = $this->service(
            $sessionGenerator,
            [$firstSession, $secondSession, $currentSession],
            80,
            10,
            4,
        )->adapt($performance);

        self::assertSame(AdaptationDecision::MAINTAIN, $result->decision);
        self::assertSame(1.0, $result->loadFactor);
        self::assertSame(66.67, $result->successRate);
        self::assertSame(0, $result->adjustedSessionsCount);
        self::assertCount(1, $plan->getAdaptationHistory());
        self::assertSame(
            'Charge actuelle maintenue.',
            $plan->getAdaptationHistory()[0]['modification'],
        );
    }

    public function testRecoveryWeekFrequencyMustBeAPositiveInteger(): void
    {
        $parameter = (new AlgorithmParameter())
            ->setParameterKey('recovery_week_frequency')
            ->setParameterValue(0);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('entier positif');
        $parameter->getRecoveryWeekFrequency();
    }

    /** @param list<Session> $recentSessions */
    private function service(
        ?SessionGeneratorService $sessionGenerator = null,
        array $recentSessions = [],
        float $successValidationRate = 80,
        float $progressionMaxPercent = 5,
        int $recoveryWeekFrequency = 4,
    ): AdaptationService
    {
        $zone = (new IntensityZone())
            ->setName('Z2')
            ->setVmaCoefMin(0.65)
            ->setVmaCoefMax(0.75)
            ->setFcmPercentMin(60)
            ->setFcmPercentMax(70);
        $repository = $this->createStub(IntensityZoneRepository::class);
        $repository->method('findOneBy')->willReturn($zone);

        $performanceRepository = $this->createStub(PerformanceRepository::class);
        $performanceRepository->method('findUserPerformances')->willReturn([]);
        $sessionRepository = $this->createStub(SessionRepository::class);
        $sessionRepository->method('findRecentForPlan')->willReturn($recentSessions);
        $parameterRepository = $this->createStub(AlgorithmParameterRepository::class);
        $parameters = [
            'success_validation_rate' => (new AlgorithmParameter())
                ->setParameterKey('success_validation_rate')
                ->setParameterValue($successValidationRate),
            'progression_max_percent' => (new AlgorithmParameter())
                ->setParameterKey('progression_max_percent')
                ->setParameterValue($progressionMaxPercent),
            'recovery_week_frequency' => (new AlgorithmParameter())
                ->setParameterKey('recovery_week_frequency')
                ->setParameterValue($recoveryWeekFrequency),
        ];
        $parameterRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?AlgorithmParameter => $parameters[$criteria['parameterKey']] ?? null,
        );

        return new AdaptationService(
            new PerformanceEvaluationService($repository),
            new ProgressScoreCalculatorService($performanceRepository),
            $sessionGenerator ?? $this->createStub(SessionGeneratorService::class),
            $sessionRepository,
            $parameterRepository,
        );
    }

    private function performance(
        float $distanceKm,
        int $durationSec,
        int $averageHeartRate,
        float $progressScore,
    ): Performance {
        $user = new User();
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user);
        $user->setProfile($profile);
        $plan = (new TrainingPlan())->setProgressScore($progressScore);
        $session = (new Session())
            ->setWeekIndex(1)
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setPlannedFcmZone('Z2')
            ->setDate(new \DateTimeImmutable('2026-09-01'));
        $plan->addSession($session);

        return (new Performance())
            ->setUser($user)
            ->setSession($session)
            ->setDistanceKm($distanceKm)
            ->setDurationSec($durationSec)
            ->setAvgHr($averageHeartRate);
    }

    private function session(string $date, string $status): Session
    {
        return (new Session())
            ->setDate(new \DateTimeImmutable($date))
            ->setStatus($status);
    }
}
