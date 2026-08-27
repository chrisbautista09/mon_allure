<?php

namespace App\Tests\Functional;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\PerformanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PerformanceRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PerformanceRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(PerformanceRepository::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFindsOnlyRequestedUsersLatestPerformancesWithSessions(): void
    {
        [$user, $plan] = $this->userWithPlan('recent-owner@example.com');
        [$otherUser, $otherPlan] = $this->userWithPlan('recent-other@example.com');
        $oldest = $this->addPerformance($user, $plan, '2026-08-20 08:00:00');
        $middle = $this->addPerformance($user, $plan, '2026-08-22 08:00:00');
        $newest = $this->addPerformance($user, $plan, '2026-08-24 08:00:00');
        $this->addPerformance($otherUser, $otherPlan, '2026-08-25 08:00:00');
        $this->entityManager->persist($user);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $performances = $this->repository->findRecentPerformances($user, 2);

        self::assertCount(2, $performances);
        self::assertSame([$newest->getId(), $middle->getId()], array_map(
            static fn (Performance $performance): ?int => $performance->getId(),
            $performances,
        ));
        self::assertNotSame($oldest->getId(), $performances[0]->getId());
        self::assertNotNull($performances[0]->getSession());
        self::assertNotNull($performances[0]->getSession()?->getTrainingPlan());
    }

    public function testLimitMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->findRecentPerformances(new User(), 0);
    }

    public function testFindsOnlyOwnedHistoryInChronologicalOrder(): void
    {
        [$user, $plan] = $this->userWithPlan('history-owner@example.com');
        [$otherUser, $otherPlan] = $this->userWithPlan('history-other@example.com');
        $latest = $this->addPerformance($user, $plan, '2026-08-24 08:00:00', 12, 4200, 180);
        $oldest = $this->addPerformance($user, $plan, '2026-08-20 08:00:00', 8, 3000, null);
        $middle = $this->addPerformance($user, $plan, '2026-08-22 08:00:00', 10, 3600, 120);
        $this->addPerformance($otherUser, $otherPlan, '2026-08-21 08:00:00', 99, 9999, 999);
        $this->entityManager->persist($user);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();

        $history = $this->repository->findByUser($user);

        self::assertSame(
            [$oldest->getId(), $middle->getId(), $latest->getId()],
            array_map(static fn (Performance $performance): ?int => $performance->getId(), $history),
        );
        self::assertSame([
            ['date' => '2026-08-20', 'value' => 8.0],
            ['date' => '2026-08-22', 'value' => 10.0],
            ['date' => '2026-08-24', 'value' => 12.0],
        ], $this->repository->findDistanceHistory($user));
        self::assertSame([
            ['date' => '2026-08-20', 'value' => 3000],
            ['date' => '2026-08-22', 'value' => 3600],
            ['date' => '2026-08-24', 'value' => 4200],
        ], $this->repository->findTimeHistory($user));
        self::assertSame([
            ['date' => '2026-08-22', 'value' => 120],
            ['date' => '2026-08-24', 'value' => 180],
        ], $this->repository->findElevationHistory($user));
    }

    public function testFindsInclusivePeriodAndRejectsInvertedDates(): void
    {
        [$user, $plan] = $this->userWithPlan('period-owner@example.com');
        $this->addPerformance($user, $plan, '2026-08-20 08:00:00');
        $expectedFirst = $this->addPerformance($user, $plan, '2026-08-21 08:00:00');
        $expectedLast = $this->addPerformance($user, $plan, '2026-08-23 08:00:00');
        $this->addPerformance($user, $plan, '2026-08-24 08:00:00');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $history = $this->repository->findByPeriod(
            $user,
            new \DateTimeImmutable('2026-08-21'),
            new \DateTimeImmutable('2026-08-23'),
        );

        self::assertSame(
            [$expectedFirst->getId(), $expectedLast->getId()],
            array_map(static fn (Performance $performance): ?int => $performance->getId(), $history),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->repository->findByPeriod(
            $user,
            new \DateTimeImmutable('2026-08-24'),
            new \DateTimeImmutable('2026-08-20'),
        );
    }

    /** @return array{User, TrainingPlan} */
    private function userWithPlan(string $email): array
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan forme')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-01'))
            ->setDurationWeeks(8);
        $user->addTrainingPlan($plan);

        return [$user, $plan];
    }

    private function addPerformance(
        User $user,
        TrainingPlan $plan,
        string $createdAt,
        float $distanceKm = 10,
        int $durationSec = 3600,
        ?int $elevationDPlus = null,
    ): Performance {
        $session = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(1)
            ->setTitle('Séance récente')
            ->setSessionType('endurance')
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setDate(new \DateTimeImmutable(substr($createdAt, 0, 10)));
        $performance = (new Performance())
            ->setDistanceKm($distanceKm)
            ->setDurationSec($durationSec)
            ->setElevationDPlus($elevationDPlus)
            ->setCreatedAt(new \DateTimeImmutable($createdAt));
        $plan->addSession($session);
        $session->setPerformance($performance);
        $user->addPerformance($performance);

        return $performance;
    }
}
