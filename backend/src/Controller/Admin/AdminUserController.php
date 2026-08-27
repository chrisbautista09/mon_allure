<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Exception\UserManagementException;
use App\Repository\UserRepository;
use App\Service\UserManagementService;
use App\Service\AdminActionLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, UserManagementService $userManagementService): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', 20);

        try {
            $users = $userManagementService->searchUsers(
                $request->query->getString('search'),
                $page,
                $perPage,
                $request->query->getString('status', 'all'),
                $request->query->getString('role', 'all'),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $total = count($users);

        return $this->json([
            'items' => array_map(
                self::userData(...),
                iterator_to_array($users),
            ),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        UserRepository $userRepository,
        UserManagementService $userManagementService,
        AdminActionLogger $adminActionLogger,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['message' => 'Jeton CSRF invalide.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $actor = $this->authenticatedAdministrator();
        $user = $userRepository->find($id);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Utilisateur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['message' => 'Le corps de la requête doit contenir un JSON valide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!array_key_exists('isActive', $data) || !is_bool($data['isActive']) || count($data) !== 1) {
            return $this->json([
                'message' => 'Seul le champ booléen isActive peut être modifié.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if ($data['isActive']) {
                $userManagementService->activateUser($user);
                $adminActionLogger->log(AdminActionLogger::ACTION_ACTIVATE, $actor, $user);
            } else {
                $userManagementService->deactivateUser($user, $actor);
                $adminActionLogger->log(AdminActionLogger::ACTION_DEACTIVATE, $actor, $user);
            }
        } catch (UserManagementException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json(['success' => true, 'user' => self::userData($user)]);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        UserRepository $userRepository,
        UserManagementService $userManagementService,
        AdminActionLogger $adminActionLogger,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('admin_users', $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['message' => 'Jeton CSRF invalide.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $actor = $this->authenticatedAdministrator();
        $user = $userRepository->find($id);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Utilisateur introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $targetId = $user->getId();
            $userManagementService->deleteUser($user, $actor);
            $adminActionLogger->log(AdminActionLogger::ACTION_DELETE, $actor, $user, $targetId);
        } catch (UserManagementException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json(['success' => true]);
    }

    /** @return array{id: int|null, email: string|null, pseudo: string|null, roles: list<string>, isActive: bool, createdAt: string|null} */
    private static function userData(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'pseudo' => $user->getPseudo(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function authenticatedAdministrator(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification administrateur requise.');
        }

        return $user;
    }
}
