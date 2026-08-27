<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryAdminTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserRepository::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->persistUser('alice.runner@example.com', 'alice-runner', true, '2026-08-20 10:00:00');
        $this->persistUser('bob.trail@example.com', 'bob-trail', false, '2026-08-22 10:00:00');
        $this->persistUser('carla@example.com', 'carla-runner', true, '2026-08-24 10:00:00');
        $this->persistUser('david@example.com', 'david', false, '2026-08-26 10:00:00');
        $this->entityManager->flush();
    }

    public function testUsersArePaginatedFromNewestToOldest(): void
    {
        $firstPage = $this->repository->findAllPaginated(1, 2);
        $secondPage = $this->repository->findAllPaginated(2, 2);

        self::assertCount(4, $firstPage);
        self::assertSame(
            ['david@example.com', 'carla@example.com'],
            array_map(static fn (User $user): ?string => $user->getEmail(), iterator_to_array($firstPage)),
        );
        self::assertSame(
            ['bob.trail@example.com', 'alice.runner@example.com'],
            array_map(static fn (User $user): ?string => $user->getEmail(), iterator_to_array($secondPage)),
        );
    }

    public function testActiveAndInactiveQueriesRemainSorted(): void
    {
        self::assertSame(
            ['carla@example.com', 'alice.runner@example.com'],
            array_map(static fn (User $user): ?string => $user->getEmail(), $this->repository->findActiveUsers()),
        );
        self::assertSame(
            ['david@example.com', 'bob.trail@example.com'],
            array_map(static fn (User $user): ?string => $user->getEmail(), $this->repository->findInactiveUsers()),
        );
    }

    public function testSearchMatchesEmailOrPseudoAndSupportsPagination(): void
    {
        $runnerResults = $this->repository->searchUsers('runner', 1, 10);
        $trailResults = $this->repository->searchUsers('trail', 1, 1);

        self::assertSame(
            ['carla@example.com', 'alice.runner@example.com'],
            array_map(static fn (User $user): ?string => $user->getEmail(), iterator_to_array($runnerResults)),
        );
        self::assertCount(1, $trailResults);
        self::assertSame('bob.trail@example.com', iterator_to_array($trailResults)[0]->getEmail());
    }

    public function testEmptySearchReturnsPaginatedUserList(): void
    {
        self::assertSame(
            ['david@example.com', 'carla@example.com'],
            array_map(
                static fn (User $user): ?string => $user->getEmail(),
                iterator_to_array($this->repository->searchUsers('  ', 1, 2)),
            ),
        );
    }

    public function testSearchCombinesStatusAndRoleFilters(): void
    {
        $this->persistUser('admin-active@example.com', 'admin-runner', true, '2026-08-27 09:00:00', ['ROLE_ADMIN']);
        $this->persistUser('admin-inactive@example.com', 'admin-paused', false, '2026-08-27 10:00:00', ['ROLE_ADMIN']);
        $this->entityManager->flush();

        self::assertSame(
            ['admin-active@example.com'],
            array_map(
                static fn (User $user): ?string => $user->getEmail(),
                iterator_to_array($this->repository->searchUsers('admin', 1, 20, 'active', 'admin')),
            ),
        );
        self::assertSame(
            ['david@example.com', 'bob.trail@example.com'],
            array_map(
                static fn (User $user): ?string => $user->getEmail(),
                iterator_to_array($this->repository->searchUsers('', 1, 20, 'inactive', 'user')),
            ),
        );
    }

    public function testSearchRejectsInvalidFilters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->searchUsers('', status: 'blocked');
    }

    public function testPaginationRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->findAllPaginated(0);
    }

    public function testSearchPaginationRejectsExcessivePageSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->searchUsers('runner', 1, 101);
    }

    public function testCountsAllAndActiveAdministrators(): void
    {
        $this->persistUser('admin-active@example.com', 'admin-active', true, '2026-08-27 09:00:00', ['ROLE_ADMIN']);
        $this->persistUser('admin-inactive@example.com', 'admin-inactive', false, '2026-08-27 10:00:00', ['ROLE_ADMIN']);
        $this->entityManager->flush();

        self::assertSame(2, $this->repository->countAdministrators());
        self::assertSame(1, $this->repository->countAdministrators(activeOnly: true));
    }

    private function persistUser(
        string $email,
        string $pseudo,
        bool $active,
        string $createdAt,
        array $roles = [],
    ): void {
        $this->entityManager->persist((new User())
            ->setEmail($email)
            ->setPseudo($pseudo)
            ->setPassword('test-password')
            ->setIsActive($active)
            ->setRoles($roles)
            ->setCreatedAt(new \DateTimeImmutable($createdAt)));
    }
}
