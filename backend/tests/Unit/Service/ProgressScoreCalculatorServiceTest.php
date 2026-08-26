<?php

namespace App\Tests\Unit\Service;

use App\Dto\PerformanceEvaluation;
use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\PerformanceEvaluationResult;
use App\Repository\PerformanceRepository;
use App\Service\ProgressScoreCalculatorService;
use PHPUnit\Framework\TestCase;

final class ProgressScoreCalculatorServiceTest extends TestCase
{
    public function testPerfectFirstPerformanceProducesMaximumReproducibleScore(): void
    {
        [$performance] = $this->performanceContext(10, 3600);
        $service = $this->serviceWithHistory([]);
        $evaluation = $this->evaluation(true);

        self::assertSame(100.0, $service->calculate($performance, $evaluation));
        self::assertSame(100.0, $service->calculate($performance, $evaluation));
    }

    public function testScoreCombinesHistoryRegularityPaceVolumeAndEvolution(): void
    {
        [$current, $plan, $user] = $this->performanceContext(9, 3600);
        $previousSession = $this->session($plan, '2026-08-25', 'completed');
        $missedSession = $this->session($plan, '2026-08-28', 'missed');
        $previous = (new Performance())
            ->setUser($user)
            ->setSession($previousSession)
            ->setDistanceKm(8)
            ->setDurationSec(3600)
            ->setCreatedAt(new \DateTimeImmutable('2026-08-25'));
        unset($missedSession);

        $score = $this->serviceWithHistory([$previous])->calculate($current, $this->evaluation(true));

        self::assertSame(84.33, $score);
    }

    public function testIntensityOutsideTargetReducesScore(): void
    {
        [$performance] = $this->performanceContext(10, 3600);
        $service = $this->serviceWithHistory([]);

        self::assertSame(96.0, $service->calculate($performance, $this->evaluation(false)));
    }

    private function serviceWithHistory(array $history): ProgressScoreCalculatorService
    {
        $repository = $this->createStub(PerformanceRepository::class);
        $repository->method('findUserPerformances')->willReturn($history);

        return new ProgressScoreCalculatorService($repository);
    }

    /** @return array{Performance, TrainingPlan, User} */
    private function performanceContext(float $distance, int $duration): array
    {
        $user = new User();
        $plan = new TrainingPlan();
        $currentSession = $this->session($plan, '2026-09-01', 'planned');
        $performance = (new Performance())
            ->setUser($user)
            ->setSession($currentSession)
            ->setDistanceKm($distance)
            ->setDurationSec($duration);

        return [$performance, $plan, $user];
    }

    private function session(TrainingPlan $plan, string $date, string $status): Session
    {
        $session = (new Session())
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setDate(new \DateTimeImmutable($date))
            ->setStatus($status);
        $plan->addSession($session);

        return $session;
    }

    private function evaluation(bool $intensityRespected): PerformanceEvaluation
    {
        return new PerformanceEvaluation(
            PerformanceEvaluationResult::OK,
            1.0,
            1.0,
            70.0,
            $intensityRespected,
        );
    }
}
