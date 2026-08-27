<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\UserManagementException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class UserManagementService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function activateUser(User $user): User
    {
        if (!$user->isActive()) {
            $user->setIsActive(true);
            $this->entityManager->flush();
        }

        return $user;
    }

    public function deactivateUser(User $user, User $actor): User
    {
        if ($this->isSameAccount($user, $actor)) {
            throw new UserManagementException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        if ($this->isAdministrator($user) && $this->userRepository->countAdministrators(activeOnly: true) <= 1) {
            throw new UserManagementException('Le dernier administrateur actif ne peut pas être désactivé.');
        }

        if ($user->isActive()) {
            $user->setIsActive(false);
            $this->entityManager->flush();
        }

        return $user;
    }

    public function deleteUser(User $user, User $actor): void
    {
        if ($this->isSameAccount($user, $actor)) {
            throw new UserManagementException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($this->isAdministrator($user) && $this->userRepository->countAdministrators() <= 1) {
            throw new UserManagementException('Le dernier administrateur ne peut pas être supprimé.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    /** @return Paginator<User> */
    public function searchUsers(
        string $search = '',
        int $page = 1,
        int $perPage = 20,
        string $status = 'all',
        string $role = 'all',
    ): Paginator
    {
        return $this->userRepository->searchUsers($search, $page, $perPage, $status, $role);
    }

    private function isAdministrator(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function isSameAccount(User $user, User $actor): bool
    {
        return $user === $actor || ($user->getId() !== null && $user->getId() === $actor->getId());
    }
}
