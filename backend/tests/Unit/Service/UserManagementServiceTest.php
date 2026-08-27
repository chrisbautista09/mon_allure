<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Exception\UserManagementException;
use App\Repository\UserRepository;
use App\Service\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

final class UserManagementServiceTest extends TestCase
{
    public function testActivatesInactiveUserAndPersistsTheChange(): void
    {
        $user = (new User())->setIsActive(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $result = $this->service(entityManager: $entityManager)->activateUser($user);

        self::assertSame($user, $result);
        self::assertTrue($user->isActive());
    }

    public function testActivationIsIdempotent(): void
    {
        $user = new User();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->service(entityManager: $entityManager)->activateUser($user);
    }

    public function testAdministratorCannotDeactivateOwnAccount(): void
    {
        $administrator = (new User())->setRoles(['ROLE_ADMIN']);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('propre compte');

        $this->service()->deactivateUser($administrator, $administrator);
    }

    public function testLastActiveAdministratorCannotBeDeactivated(): void
    {
        $target = (new User())->setRoles(['ROLE_ADMIN']);
        $actor = (new User())->setRoles(['ROLE_ADMIN']);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('countAdministrators')->with(true)->willReturn(1);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('dernier administrateur actif');

        $this->service($repository)->deactivateUser($target, $actor);
    }

    public function testRegularUserCanBeDeactivated(): void
    {
        $target = new User();
        $actor = (new User())->setRoles(['ROLE_ADMIN']);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->service(entityManager: $entityManager)->deactivateUser($target, $actor);

        self::assertFalse($target->isActive());
    }

    public function testAdministratorCannotDeleteOwnAccount(): void
    {
        $administrator = (new User())->setRoles(['ROLE_ADMIN']);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('propre compte');

        $this->service()->deleteUser($administrator, $administrator);
    }

    public function testLastAdministratorCannotBeDeleted(): void
    {
        $target = (new User())->setRoles(['ROLE_ADMIN']);
        $actor = (new User())->setRoles(['ROLE_ADMIN']);
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('countAdministrators')->with(false)->willReturn(1);

        $this->expectException(UserManagementException::class);
        $this->expectExceptionMessage('dernier administrateur');

        $this->service($repository)->deleteUser($target, $actor);
    }

    public function testUserCanBeDeletedWhenBusinessRulesAllowIt(): void
    {
        $target = new User();
        $actor = (new User())->setRoles(['ROLE_ADMIN']);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($target);
        $entityManager->expects(self::once())->method('flush');

        $this->service(entityManager: $entityManager)->deleteUser($target, $actor);
    }

    public function testSearchDelegatesPaginationToRepository(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $paginator = $this->createStub(Paginator::class);
        $repository->expects(self::once())
            ->method('searchUsers')
            ->with('runner', 2, 25, 'inactive', 'admin')
            ->willReturn($paginator);

        self::assertSame($paginator, $this->service($repository)->searchUsers('runner', 2, 25, 'inactive', 'admin'));
    }

    private function service(
        ?UserRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
    ): UserManagementService {
        return new UserManagementService(
            $repository ?? $this->createStub(UserRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
