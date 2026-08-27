<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\HistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/history', name: 'api_history_')]
#[IsGranted('ROLE_USER')]
final class HistoryController extends AbstractController
{
    #[Route('/sessions', name: 'sessions', methods: ['GET'])]
    public function sessions(Request $request, HistoryService $historyService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $page = $this->positiveInteger($request->query->get('page', '1'));
        $perPage = $this->positiveInteger($request->query->get('perPage', '20'));

        if ($page === null || $perPage === null || $perPage > 100) {
            return $this->json([
                'message' => 'Les paramètres de pagination sont invalides.',
                'constraints' => [
                    'page' => 'Entier supérieur ou égal à 1.',
                    'perPage' => 'Entier compris entre 1 et 100.',
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($historyService->getSessionHistory($user, $page, $perPage));
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return ctype_digit($value) && (int) $value >= 1 ? (int) $value : null;
    }
}
