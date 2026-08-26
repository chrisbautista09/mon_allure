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
            ->setDistanceKm(10)
            ->setDurationSec(3600)
            ->setCreatedAt(new \DateTimeImmutable($createdAt));
        $plan->addSession($session);
        $session->setPerformance($performance);
        $user->addPerformance($performance);

        return $performance;
    }
}
