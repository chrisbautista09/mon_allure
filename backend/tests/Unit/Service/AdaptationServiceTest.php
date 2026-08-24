<?php

namespace App\Tests\Unit\Service;

use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\AdaptationDecision;
use App\Repository\IntensityZoneRepository;
use App\Repository\PerformanceRepository;
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
        $currentSession->setWeekIndex(3)->setStatus('planned');
        $plan->addSession((new Session())
            ->setWeekIndex(1)
            ->setStatus('done')
            ->setDate(new \DateTimeImmutable('2026-08-18')));
        $plan->addSession((new Session())
            ->setWeekIndex(2)
            ->setStatus('missed')
            ->setDate(new \DateTimeImmutable('2026-08-25')));
        $futureSession = (new Session())
            ->setWeekIndex(4)
            ->setStatus('planned')
            ->setDate(new \DateTimeImmutable('2026-09-08'));
        $plan->addSession($futureSession);
        $sessionGenerator = $this->createMock(SessionGeneratorService::class);
        $sessionGenerator
            ->expects(self::once())
            ->method('recalibrateFutureSessions')
            ->with($plan, $currentSession, 1.05)
            ->willReturn([$futureSession]);

        $result = $this->service($sessionGenerator)->adapt($performance);

        self::assertSame(1, $result->adjustedSessionsCount);
    }

    private function service(?SessionGeneratorService $sessionGenerator = null): AdaptationService
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

        return new AdaptationService(
            new PerformanceEvaluationService($repository),
            new ProgressScoreCalculatorService($performanceRepository),
            $sessionGenerator ?? $this->createStub(SessionGeneratorService::class),
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
}
