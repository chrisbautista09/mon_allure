<?php

namespace App\Controller;

use App\Entity\Session;
use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use App\Service\ProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class UserDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        TrainingPlanRepository $trainingPlanRepository,
        ProgressService $progressService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        if ($user->getProfile() === null) {
            return $this->redirectToRoute('app_profile_calibration');
        }

        $plan = $trainingPlanRepository->findLatestActiveOwnedWithSessions($user);
        if ($plan === null) {
            return $this->redirectToRoute('app_training_goal');
        }

        if ($progressService->synchronizeCurrentWeek($plan)) {
            $entityManager->flush();
        }

        $today = new \DateTimeImmutable('today');
        $nextSession = null;
        foreach ($plan->getSessions() as $session) {
            if ($session->getDate() !== null
                && $session->getDate() >= $today
                && $session->getStatus() === 'planned') {
                $nextSession = $session;
                break;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'plan' => $plan,
            'nextSession' => $nextSession,
            'progressPercentage' => $progressService->calculatePlanProgress($plan),
            'sportsProgress' => $progressService->getSportsProgress($plan),
            'currentPhase' => $progressService->getCurrentPhase($plan),
        ]);
    }
}
