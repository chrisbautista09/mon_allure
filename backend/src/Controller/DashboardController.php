<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FormStatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard', name: 'api_dashboard_')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/form-status', name: 'form_status', methods: ['GET'])]
    public function formStatus(FormStatusService $formStatusService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $formStatus = $formStatusService->calculate($user);

        return $this->json([
            ...$formStatus->toArray(),
            'last_update' => $formStatus->calculatedAt->format('Y-m-d'),
        ]);
    }
}
