<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\HistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SessionHistoryController extends AbstractController
{
    private const ALLOWED_STATUSES = ['completed', 'missed', 'cancelled'];
    private const ALLOWED_PERIODS = ['30_days', '3_months', 'year'];

    #[Route('/history/sessions', name: 'app_history_sessions', methods: ['GET'])]
    public function index(Request $request, HistoryService $historyService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $requestedStatus = strtolower(trim($request->query->getString('status', 'all')));
        $status = in_array($requestedStatus, self::ALLOWED_STATUSES, true) ? $requestedStatus : null;
        $requestedPeriod = strtolower(trim($request->query->getString('period', 'all')));
        $period = in_array($requestedPeriod, self::ALLOWED_PERIODS, true) ? $requestedPeriod : null;
        $search = mb_substr(trim($request->query->getString('search')), 0, 100);
        $filters = [
            'status' => $status ?? 'all',
            'period' => $period ?? 'all',
            'search' => $search,
        ];
        $history = $historyService->getSessionHistory($user, $page, 10, $status, $period, $search);
        $totalPages = $history['pagination']['totalPages'];

        if ($totalPages > 0 && $page > $totalPages) {
            return $this->redirectToRoute('app_history_sessions', [
                ...$filters,
                'page' => $totalPages,
            ]);
        }

        return $this->render('history/index.html.twig', [
            'sessions' => $history['items'],
            'pagination' => $history['pagination'],
            'filters' => $filters,
        ]);
    }
}
