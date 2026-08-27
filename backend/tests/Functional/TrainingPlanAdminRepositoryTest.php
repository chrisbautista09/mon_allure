<?php

namespace App\Tests\Functional;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TrainingPlanAdminRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TrainingPlanRepository $repository;
    private User $firstUser;
    private User $secondUser;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->repository = self::getContainer()->get(TrainingPlanRepository::class);

        $this->firstUser = $this->persistUser('runner-one@example.com');
        $this->secondUser = $this->persistUser('runner-two@example.com');
        $this->persistPlan('Ancien plan', $this->firstUser, 'OPTIMAL', 85, '2026-08-01 08:00:00');
        $this->persistPlan('Progression faible', $this->firstUser, 'MOYEN', 20, '2026-08-02 08:00:00');
        $this->persistPlan('Plan impossible', $this->secondUser, 'IMPOSSIBLE', 80, '2026-08-03 08:00:00');
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testAllPlansAreJoinedWithUsersSortedAndPaginated(): void
    {
        $firstPage = $this->repository->findAllForAdmin(page: 1, perPage: 2);

        self::assertCount(3, $firstPage);
        self::assertSame(
            ['Plan impossible', 'Progression faible'],
            array_map(
                static fn (TrainingPlan $plan): ?string => $plan->getName(),
                iterator_to_array($firstPage),
            ),
        );
        self::assertSame('runner-two@example.com', iterator_to_array($firstPage)[0]->getUser()?->getEmail());

        $secondPage = iterator_to_array($this->repository->findAllForAdmin(page: 2, perPage: 2));
        self::assertCount(1, $secondPage);
        self::assertSame('Ancien plan', $secondPage[0]->getName());
    }

    public function testPlansCanBeFilteredByFeasibilityAndUser(): void
    {
        $impossible = iterator_to_array($this->repository->findByFeasibilityStatus('impossible'));
        self::assertCount(1, $impossible);
        self::assertSame('Plan impossible', $impossible[0]->getName());

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'runner-one@example.com']);
        self::assertInstanceOf(User::class, $user);
        $ownedPlans = iterator_to_array($this->repository->findByUser($user));
        self::assertCount(2, $ownedPlans);
        self::assertSame('Progression faible', $ownedPlans[0]->getName());
    }

    public function testProblematicPlansIncludeImpossibleOrLowProgressPlans(): void
    {
        $plans = iterator_to_array($this->repository->findProblematicPlans());

        self::assertSame(
            ['Plan impossible', 'Progression faible'],
            array_map(static fn (TrainingPlan $plan): ?string => $plan->getName(), $plans),
        );
    }

    public function testAdministrativeFiltersCanBeCombined(): void
    {
        $plans = iterator_to_array($this->repository->searchForAdmin(
            feasibility: 'acceptable',
            userSearch: 'runner-one',
        ));

        self::assertCount(1, $plans);
        self::assertSame('Progression faible', $plans[0]->getName());
    }

    public function testAdministrativeStatusFiltersDistinguishActiveCompletedAndArchivedPlans(): void
    {
        $completed = $this->repository->findOneBy(['name' => 'Ancien plan']);
        $archived = $this->repository->findOneBy(['name' => 'Progression faible']);
        self::assertInstanceOf(TrainingPlan::class, $completed);
        self::assertInstanceOf(TrainingPlan::class, $archived);
        $completed->setEndDate(new \DateTimeImmutable('2026-08-10'));
        $archived->setIsActive(false);
        $this->entityManager->flush();

        self::assertSame(
            ['Plan impossible'],
            $this->names($this->repository->searchForAdmin(status: 'ACTIVE')),
        );
        self::assertSame(
            ['Ancien plan'],
            $this->names($this->repository->searchForAdmin(status: 'COMPLETED')),
        );
        self::assertSame(
            ['Progression faible'],
            $this->names($this->repository->searchForAdmin(status: 'ARCHIVED')),
        );
    }

    public function testInvalidAdministrativeFiltersAreRejected(): void
    {
        foreach ([
            static fn (TrainingPlanRepository $repository) => $repository->searchForAdmin(feasibility: 'UNKNOWN'),
            static fn (TrainingPlanRepository $repository) => $repository->searchForAdmin(status: 'PAUSED'),
        ] as $query) {
            try {
                $query($this->repository);
                self::fail('Un filtre administratif invalide doit être refusé.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testPaginationArgumentsAndEmptyFeasibilityAreValidated(): void
    {
        foreach ([
            static fn (TrainingPlanRepository $repository) => $repository->findAllForAdmin(0),
            static fn (TrainingPlanRepository $repository) => $repository->findAllForAdmin(1, 101),
            static fn (TrainingPlanRepository $repository) => $repository->findByFeasibilityStatus(' '),
        ] as $query) {
            try {
                $query($this->repository);
                self::fail('Une requête administrative invalide doit être refusée.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function persistUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $this->entityManager->persist($user);

        return $user;
    }

    /** @return list<string|null> */
    private function names(iterable $plans): array
    {
        return array_map(
            static fn (TrainingPlan $plan): ?string => $plan->getName(),
            iterator_to_array($plans),
        );
    }

    private function persistPlan(
        string $name,
        User $user,
        string $feasibility,
        float $progress,
        string $createdAt,
    ): void {
        $plan = (new TrainingPlan())
            ->setName($name)
            ->setPoleType('discovery')
            ->setTargetType('distance')
            ->setTargetValue(5)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator($feasibility)
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-09-26'))
            ->setDurationWeeks(8)
            ->setProgressScore($progress)
            ->setCreatedAt(new \DateTimeImmutable($createdAt))
            ->setUser($user);
        $this->entityManager->persist($plan);
    }
}
