<?php

namespace App\Tests\Unit\Service;

use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\TrainingPlan;
use App\Repository\IntensityZoneRepository;
use App\Service\PaceCalculatorService;
use App\Service\SessionGeneratorService;
use PHPUnit\Framework\TestCase;

final class SessionGeneratorServiceTest extends TestCase
{
    public function testGeneratesThreeDatedAndInstructedSessionsPerWeek(): void
    {
        $plan = $this->plan(2);
        $sessions = $this->service()->generate($plan, $this->profile(), 3);

        self::assertCount(6, $sessions);
        self::assertCount(6, $plan->getSessions());

        foreach ($sessions as $session) {
            self::assertNotNull($session->getDate());
            self::assertNotSame('', $session->getDescription());
            self::assertSame($plan, $session->getTrainingPlan());
            self::assertSame('planned', $session->getStatus());
            self::assertGreaterThan(0, $session->getPlannedDurationMin());
            self::assertGreaterThan(0, $session->getPlannedDistanceKm());
            self::assertCount(1, $session->getSessionIntensityZones());
        }

        self::assertSame(
            ['endurance', 'threshold', 'long_run'],
            array_map(static fn ($session) => $session->getSessionType(), array_slice($sessions, 0, 3))
        );
        self::assertSame([2, 4, 7], array_map(
            static fn ($session) => $session->getDayOfWeek(),
            array_slice($sessions, 0, 3)
        ));
        self::assertSame('2026-09-01', $sessions[0]->getDate()?->format('Y-m-d'));
        self::assertSame('2026-09-06', $sessions[2]->getDate()?->format('Y-m-d'));
        self::assertSame('2026-09-08', $sessions[3]->getDate()?->format('Y-m-d'));
    }

    public function testDiscoveryGeneratesTwoSessionsPerWeek(): void
    {
        $sessions = $this->service()->generate(
            $this->plan(1, 'discovery'),
            $this->profile(),
            2,
        );

        self::assertCount(2, $sessions);
        self::assertSame(
            ['endurance', 'long_run'],
            array_map(static fn ($session) => $session->getSessionType(), $sessions),
        );
        self::assertSame([2, 7], array_map(
            static fn ($session) => $session->getDayOfWeek(),
            $sessions,
        ));
    }

    public function testPerformanceGeneratesFiveVariedSessionsPerWeek(): void
    {
        $sessions = $this->service()->generate(
            $this->plan(1, 'performance'),
            $this->profile(),
            5,
        );

        self::assertCount(5, $sessions);
        self::assertSame(
            ['recovery', 'threshold', 'endurance', 'vma', 'long_run'],
            array_map(static fn ($session) => $session->getSessionType(), $sessions),
        );
        self::assertSame([1, 2, 4, 5, 7], array_map(
            static fn ($session) => $session->getDayOfWeek(),
            $sessions,
        ));
    }

    public function testEveryFourthWeekIsLighter(): void
    {
        $sessions = $this->service()->generate($this->plan(4), $this->profile(), 3);

        $weekThreeLongRun = $sessions[8];
        $weekFourLongRun = $sessions[11];

        self::assertSame('Sortie longue', $weekThreeLongRun->getTitle());
        self::assertSame('Sortie longue allégée', $weekFourLongRun->getTitle());
        self::assertLessThan(
            $weekThreeLongRun->getPlannedDurationMin(),
            $weekFourLongRun->getPlannedDurationMin()
        );
    }

    public function testElevationIsDistributedAcrossLongRuns(): void
    {
        $sessions = $this->service()->generate($this->plan(2), $this->profile(), 3);

        self::assertNull($sessions[0]->getPlannedElevationDPlus());
        self::assertSame(450, $sessions[2]->getPlannedElevationDPlus());
        self::assertSame(450, $sessions[5]->getPlannedElevationDPlus());
    }

    public function testRejectsDuplicateGeneration(): void
    {
        $plan = $this->plan(1);
        $service = $this->service();
        $service->generate($plan, $this->profile(), 3);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('déjà été générées');

        $service->generate($plan, $this->profile(), 3);
    }

    public function testRequiredZonesMustExist(): void
    {
        $repository = $this->createStub(IntensityZoneRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $service = new SessionGeneratorService($repository, new PaceCalculatorService());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Z2');

        $service->generate($this->plan(1), $this->profile(), 3);
    }

    public function testRecalibratesOnlyFuturePlannedSessionsWithoutMovingDates(): void
    {
        $plan = $this->plan(4);
        $reference = $this->adjustableSession('2026-09-01', 'completed', 'endurance');
        $future = $this->adjustableSession('2026-09-08', 'planned', 'threshold');
        $past = $this->adjustableSession('2026-08-31', 'planned', 'threshold');
        $completed = $this->adjustableSession('2026-09-09', 'completed', 'threshold');
        $goalEvent = $this->adjustableSession('2026-09-10', 'planned', 'goal_event');

        foreach ([$reference, $future, $past, $completed, $goalEvent] as $session) {
            $plan->addSession($session);
        }

        $adjusted = $this->service()->recalibrateFutureSessions($plan, $reference, 1.05);

        self::assertSame([$future], $adjusted);
        self::assertSame('2026-09-08', $future->getDate()?->format('Y-m-d'));
        self::assertSame(42, $future->getPlannedDurationMin());
        self::assertSame(8.4, $future->getPlannedDistanceKm());
        self::assertSame(105, $future->getPlannedElevationDPlus());
        self::assertSame(0.945, $future->getPlannedVmaCoef());
        self::assertSame(40, $past->getPlannedDurationMin());
        self::assertSame(40, $completed->getPlannedDurationMin());
        self::assertSame(40, $goalEvent->getPlannedDurationMin());
    }

    public function testLoadReductionRespectsSafePositiveValues(): void
    {
        $plan = $this->plan(4);
        $reference = $this->adjustableSession('2026-09-01', 'completed', 'endurance');
        $future = $this->adjustableSession('2026-09-08', 'planned', 'vma');
        $plan->addSession($reference)->addSession($future);

        $this->service()->recalibrateFutureSessions($plan, $reference, 0.95);

        self::assertSame(38, $future->getPlannedDurationMin());
        self::assertSame(7.6, $future->getPlannedDistanceKm());
        self::assertSame(95, $future->getPlannedElevationDPlus());
        self::assertSame(0.855, $future->getPlannedVmaCoef());
    }

    public function testRegeneratesUpcomingSessionsWithoutChangingHistoryOrGoalEvent(): void
    {
        $plan = $this->plan(4);
        $reference = $this->adjustableSession('2026-09-01', 'completed', 'endurance');
        $future = $this->adjustableSession('2026-09-08', 'planned', 'threshold');
        $past = $this->adjustableSession('2026-08-31', 'completed', 'threshold');
        $goalEvent = $this->adjustableSession('2026-09-10', 'planned', 'goal_event');
        $plan->addSession($reference)->addSession($future)->addSession($past)->addSession($goalEvent);

        $regenerated = $this->service()->regenerateUpcoming($plan, $reference, 1.10);

        self::assertCount(1, $regenerated);
        $replacement = $regenerated[0];
        self::assertNotSame($future, $replacement);
        self::assertFalse($plan->getSessions()->contains($future));
        self::assertTrue($plan->getSessions()->contains($replacement));
        self::assertSame('2026-09-08', $replacement->getDate()?->format('Y-m-d'));
        self::assertSame(44, $replacement->getPlannedDurationMin());
        self::assertSame(8.8, $replacement->getPlannedDistanceKm());
        self::assertSame(0.99, $replacement->getPlannedVmaCoef());
        self::assertTrue($plan->getSessions()->contains($past));
        self::assertTrue($plan->getSessions()->contains($reference));
        self::assertTrue($plan->getSessions()->contains($goalEvent));
    }

    public function testRegenerationNeverReplacesASessionWithPerformanceHistory(): void
    {
        $plan = $this->plan(4);
        $reference = $this->adjustableSession('2026-09-01', 'completed', 'endurance');
        $protectedSession = $this->adjustableSession('2026-09-08', 'planned', 'threshold');
        $performance = (new Performance())
            ->setDistanceKm(8)
            ->setDurationSec(2400);
        $protectedSession->setPerformance($performance);
        $plan->addSession($reference)->addSession($protectedSession);

        $regenerated = $this->service()->regenerateUpcoming($plan, $reference, 1.10);

        self::assertSame([], $regenerated);
        self::assertTrue($plan->getSessions()->contains($protectedSession));
        self::assertSame($protectedSession, $performance->getSession());
        self::assertSame(40, $protectedSession->getPlannedDurationMin());
    }

    private function service(): SessionGeneratorService
    {
        $zones = [
            'Z1' => $this->zone('Z1', 0.50, 0.65, 50, 60),
            'Z2' => $this->zone('Z2', 0.65, 0.75, 70, 80),
            'Z4' => $this->zone('Z4', 0.85, 0.95, 85, 95),
            'Z5' => $this->zone('Z5', 0.95, 1.05, 90, 100),
        ];
        $repository = $this->createStub(IntensityZoneRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => $zones[$criteria['name']] ?? null
        );

        return new SessionGeneratorService($repository, new PaceCalculatorService());
    }

    private function zone(
        string $name,
        float $vmaMin,
        float $vmaMax,
        float $fcmMin,
        float $fcmMax,
    ): IntensityZone {
        return (new IntensityZone())
            ->setName($name)
            ->setVmaCoefMin($vmaMin)
            ->setVmaCoefMax($vmaMax)
            ->setFcmPercentMin($fcmMin)
            ->setFcmPercentMax($fcmMax);
    }

    private function profile(): Profile
    {
        return (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(16)
            ->setFcm(190);
    }

    private function adjustableSession(string $date, string $status, string $type): \App\Entity\Session
    {
        return (new \App\Entity\Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(2)
            ->setTitle('Séance adaptable')
            ->setSessionType($type)
            ->setPlannedDurationMin(40)
            ->setPlannedDistanceKm(8)
            ->setPlannedElevationDPlus(100)
            ->setPlannedVmaCoef(0.9)
            ->setDate(new \DateTimeImmutable($date))
            ->setStatus($status);
    }

    private function plan(int $weeks, string $poleType = 'intermediate'): TrainingPlan
    {
        return (new TrainingPlan())
            ->setPoleType($poleType)
            ->setStartDate(new \DateTimeImmutable('2026-08-31'))
            ->setEndDate(new \DateTimeImmutable(sprintf('2026-08-31 +%d weeks -1 day', $weeks)))
            ->setDurationWeeks($weeks)
            ->setElevationTargetDPlus(900);
    }
}
