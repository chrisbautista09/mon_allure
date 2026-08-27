<?php

namespace App\Tests\Functional;

use App\Entity\IntensityZone;
use App\Entity\Session;
use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\SessionIntensityZoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SessionIntensityZoneRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SessionIntensityZoneRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(SessionIntensityZoneRepository::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFindDistributionByUserAggregatesOnlyOwnedSessions(): void
    {
        [$user, $plan] = $this->userWithPlan('zones-owner@example.com');
        [$otherUser, $otherPlan] = $this->userWithPlan('zones-other@example.com');
        $z1 = $this->zone('Z1', 0.5, 0.6, 50, 60);
        $z2 = $this->zone('Z2', 0.6, 0.7, 60, 70);
        $this->entityManager->persist($z1);
        $this->entityManager->persist($z2);

        $this->addSession($plan, 1, [[$z1, 60], [$z2, 40]]);
        $this->addSession($plan, 2, [[$z1, 80], [$z2, 20]]);
        $this->addSession($otherPlan, 1, [[$z2, 100]]);
        $this->entityManager->persist($user);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();

        self::assertSame([
            [
                'zoneId' => $z1->getId(),
                'name' => 'Z1',
                'occurrenceCount' => 2,
                'durationPercentTotal' => 140.0,
            ],
            [
                'zoneId' => $z2->getId(),
                'name' => 'Z2',
                'occurrenceCount' => 2,
                'durationPercentTotal' => 60.0,
            ],
        ], $this->repository->findDistributionByUser($user));
        self::assertSame(2, $this->repository->countDistinctSessionsByUser($user));
        self::assertSame([
            [
                'zoneId' => $z1->getId(),
                'name' => 'Z1',
                'occurrenceCount' => 1,
                'durationPercentTotal' => 80.0,
            ],
            [
                'zoneId' => $z2->getId(),
                'name' => 'Z2',
                'occurrenceCount' => 1,
                'durationPercentTotal' => 20.0,
            ],
        ], $this->repository->findDistributionByUser(
            $user,
            new \DateTimeImmutable('2026-08-02'),
            new \DateTimeImmutable('2026-08-02'),
        ));
        self::assertSame(1, $this->repository->countDistinctSessionsByUser(
            $user,
            new \DateTimeImmutable('2026-08-02'),
            new \DateTimeImmutable('2026-08-02'),
        ));
    }

    public function testFindDistributionByTrainingPlanDoesNotMixUsersOrPlans(): void
    {
        [$user, $firstPlan] = $this->userWithPlan('plan-zones@example.com');
        $secondPlan = $this->plan('Second plan');
        $user->addTrainingPlan($secondPlan);
        $z1 = $this->zone('Z1', 0.5, 0.6, 50, 60);
        $z3 = $this->zone('Z3', 0.7, 0.8, 70, 80);
        $this->entityManager->persist($z1);
        $this->entityManager->persist($z3);

        $this->addSession($firstPlan, 1, [[$z1, 30], [$z3, 70]]);
        $this->addSession($secondPlan, 1, [[$z1, 100]]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertSame([
            [
                'zoneId' => $z1->getId(),
                'name' => 'Z1',
                'occurrenceCount' => 1,
                'durationPercentTotal' => 30.0,
            ],
            [
                'zoneId' => $z3->getId(),
                'name' => 'Z3',
                'occurrenceCount' => 1,
                'durationPercentTotal' => 70.0,
            ],
        ], $this->repository->findDistributionByTrainingPlan($firstPlan));
        self::assertSame(1, $this->repository->countDistinctSessionsByTrainingPlan($firstPlan));
    }

    public function testEmptyDistributionReturnsAnEmptyList(): void
    {
        [$user] = $this->userWithPlan('empty-zones@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertSame([], $this->repository->findDistributionByUser($user));
        self::assertSame(0, $this->repository->countDistinctSessionsByUser($user));
    }

    /** @return array{User, TrainingPlan} */
    private function userWithPlan(string $email): array
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = $this->plan('Plan zones');
        $user->addTrainingPlan($plan);

        return [$user, $plan];
    }

    private function plan(string $name): TrainingPlan
    {
        return (new TrainingPlan())
            ->setName($name)
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-01'))
            ->setDurationWeeks(8);
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

    /** @param list<array{IntensityZone, int}> $distributions */
    private function addSession(TrainingPlan $plan, int $day, array $distributions): void
    {
        $session = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek($day)
            ->setTitle(sprintf('Séance %d', $day))
            ->setSessionType('endurance')
            ->setDate(new \DateTimeImmutable(sprintf('2026-08-%02d', $day)));

        foreach ($distributions as [$zone, $percentage]) {
            $session->addSessionIntensityZone(
                (new SessionIntensityZone())
                    ->setIntensityZone($zone)
                    ->setDurationPercent($percentage),
            );
        }

        $plan->addSession($session);
    }
}
