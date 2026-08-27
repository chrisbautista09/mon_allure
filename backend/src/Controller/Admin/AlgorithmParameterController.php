<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AdminActionLogger;
use App\Service\AlgorithmParameterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/algorithm-parameters', name: 'api_admin_algorithm_parameters_')]
#[IsGranted('ROLE_ADMIN')]
final class AlgorithmParameterController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'admin_algorithm_parameters';

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(
        AlgorithmParameterService $parameterService,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        return $this->json([
            'parameters' => $parameterService->getCurrentParameters(),
            'csrfToken' => $csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]);
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(
        Request $request,
        AlgorithmParameterService $parameterService,
        AdminActionLogger $adminActionLogger,
    ): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['message' => 'Jeton CSRF invalide.'], JsonResponse::HTTP_FORBIDDEN);
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['message' => 'Le corps de la requête doit contenir un JSON valide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (array_keys($data) !== ['parameters'] || !is_array($data['parameters'])) {
            return $this->json([
                'message' => 'Le champ parameters doit être un objet JSON et aucun autre champ n’est autorisé.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $previousParameters = $parameterService->getCurrentParameters();
            $parameters = $parameterService->updateParameters($data['parameters']);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\LogicException $exception) {
            return $this->json(['message' => $exception->getMessage()], JsonResponse::HTTP_CONFLICT);
        }

        $administrator = $this->authenticatedAdministrator();
        foreach ($data['parameters'] as $key => $requestedValue) {
            $oldValue = $previousParameters[$key] ?? null;
            $newValue = $parameters[$key] ?? null;
            if ($oldValue === null || $newValue === null || (float) $oldValue === (float) $newValue) {
                continue;
            }

            $adminActionLogger->logParameterChange(
                $administrator,
                $key,
                (float) $oldValue,
                (float) $newValue,
            );
        }

        return $this->json(['success' => true, 'parameters' => $parameters]);
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
