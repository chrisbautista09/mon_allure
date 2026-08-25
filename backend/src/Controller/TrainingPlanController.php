<?php

namespace App\Controller;

use App\Dto\TrainingPlanDTO;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use App\Service\PdfGeneratorService;
use App\Service\TrainingPlanGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/training-plans', name: 'api_training_plans_')]
final class TrainingPlanController extends AbstractController
{
    #[Route('/{id}/export', name: 'export', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function export(
        int $id,
        TrainingPlanRepository $repository,
        PdfGeneratorService $pdfGenerator,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $plan = $repository->findOneOwnedWithSessions($id, $user);

        if (!$plan instanceof TrainingPlan) {
            return $this->json(['message' => 'Plan d’entraînement introuvable.'], 404);
        }

        if ($plan->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez exporter que vos propres plans.');
        }

        $response = new Response($pdfGenerator->generateTrainingPlanPdf($plan));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('plan-entrainement-%d.pdf', $id),
        ));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', 'sandbox');

        return $response;
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TrainingPlanRepository $repository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $plan = $repository->findOneOwnedWithSessions($id, $user);

        if (!$plan instanceof TrainingPlan) {
            return $this->json(['message' => 'Plan d’entraînement introuvable.'], 404);
        }

        return $this->json($this->planData($plan));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        TrainingPlanGeneratorService $generator,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->json(['message' => 'Le corps de la requête doit contenir un JSON valide.'], 400);
        }

        $typeErrors = $this->typeErrors($data);

        if ($typeErrors !== []) {
            return $this->json([
                'message' => 'Les données du plan sont invalides.',
                'errors' => $typeErrors,
            ], 422);
        }

        $goal = $this->goalFromData($data);

        try {
            /** @var TrainingPlan $plan */
            $plan = $entityManager->wrapInTransaction(function () use (
                $generator,
                $user,
                $goal,
                $entityManager,
            ): TrainingPlan {
                $plan = $generator->generatePlan($user, $goal);
                $entityManager->persist($plan);

                return $plan;
            });
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }

        return $this->json([
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'poleType' => $plan->getPoleType(),
            'feasibilityIndicator' => $plan->getFeasibilityIndicator(),
            'startDate' => $plan->getStartDate()?->format('Y-m-d'),
            'endDate' => $plan->getEndDate()?->format('Y-m-d'),
            'durationWeeks' => $plan->getDurationWeeks(),
            'sessionsCount' => $plan->getSessions()->count(),
        ], 201);
    }

    /** @param array<string, mixed> $data */
    private function goalFromData(array $data): TrainingPlanDTO
    {
        $goal = new TrainingPlanDTO();
        $goal->poleType = $data['poleType'] ?? null;
        $goal->targetType = $data['targetType'] ?? null;
        $goal->targetValue = isset($data['targetValue']) ? (float) $data['targetValue'] : null;
        $goal->targetUnit = $data['targetUnit'] ?? null;
        $goal->terrainType = $data['terrainType'] ?? null;
        $goal->elevationTargetDPlus = isset($data['elevationTargetDPlus'])
            ? (int) $data['elevationTargetDPlus']
            : null;

        return $goal;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, list<string>>
     */
    private function typeErrors(array $data): array
    {
        $errors = [];

        foreach (['poleType', 'targetType', 'targetUnit', 'terrainType'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field][] = 'Cette valeur doit être une chaîne de caractères.';
            }
        }

        if (isset($data['targetValue'])
            && !is_int($data['targetValue'])
            && !is_float($data['targetValue'])) {
            $errors['targetValue'][] = 'Cette valeur doit être un nombre.';
        }

        if (isset($data['elevationTargetDPlus']) && !is_int($data['elevationTargetDPlus'])) {
            $errors['elevationTargetDPlus'][] = 'Cette valeur doit être un nombre entier.';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function planData(TrainingPlan $plan): array
    {
        $weeks = [];

        foreach ($plan->getSessions() as $session) {
            $week = (int) $session->getWeekIndex();
            $weeks[$week] ??= ['week' => $week, 'sessions' => []];
            $weeks[$week]['sessions'][] = [
                'id' => $session->getId(),
                'date' => $session->getDate()?->format('Y-m-d'),
                'dayOfWeek' => $session->getDayOfWeek(),
                'title' => $session->getTitle(),
                'description' => $session->getDescription(),
                'type' => $session->getSessionType(),
                'plannedDistanceKm' => $session->getPlannedDistanceKm(),
                'plannedDurationMin' => $session->getPlannedDurationMin(),
                'plannedElevationDPlus' => $session->getPlannedElevationDPlus(),
                'plannedVmaCoef' => $session->getPlannedVmaCoef(),
                'plannedFcmZone' => $session->getPlannedFcmZone(),
                'status' => $session->getStatus(),
            ];
        }

        ksort($weeks);

        return [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'poleType' => $plan->getPoleType(),
            'targetType' => $plan->getTargetType(),
            'targetValue' => $plan->getTargetValue(),
            'targetUnit' => $plan->getTargetUnit(),
            'terrainType' => $plan->getTerrainType(),
            'elevationTargetDPlus' => $plan->getElevationTargetDPlus(),
            'feasibilityIndicator' => $plan->getFeasibilityIndicator(),
            'startDate' => $plan->getStartDate()?->format('Y-m-d'),
            'endDate' => $plan->getEndDate()?->format('Y-m-d'),
            'durationWeeks' => $plan->getDurationWeeks(),
            'currentWeek' => $plan->getCurrentWeek(),
            'progressScore' => $plan->getProgressScore(),
            'weeks' => array_values($weeks),
        ];
    }
}
