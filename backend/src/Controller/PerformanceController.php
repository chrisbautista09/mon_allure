<?php

namespace App\Controller;

use App\Dto\PerformanceDTO;
use App\Entity\Performance;
use App\Entity\User;
use App\Repository\PerformanceRepository;
use App\Repository\SessionRepository;
use App\Service\AdaptationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/performances', name: 'api_performances_')]
final class PerformanceController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        SessionRepository $sessionRepository,
        PerformanceRepository $performanceRepository,
        AdaptationService $adaptationService,
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
                'message' => 'Les données de performance sont invalides.',
                'errors' => $typeErrors,
            ], 422);
        }

        $performanceData = $this->dtoFromData($data);
        $violations = $validator->validate($performanceData);

        if (count($violations) > 0) {
            return $this->json([
                'message' => 'Les données de performance sont invalides.',
                'errors' => $this->violationErrors($violations),
            ], 422);
        }

        $session = $sessionRepository->findOneOwned((int) $performanceData->sessionId, $user);

        if ($session === null) {
            return $this->json(['message' => 'Séance introuvable.'], 404);
        }

        if ($performanceRepository->findOneBySessionAndUser($session, $user) !== null) {
            return $this->json(['message' => 'Une performance existe déjà pour cette séance.'], 409);
        }

        if ($performanceData->terrainType !== $session->getTrainingPlan()?->getTerrainType()) {
            return $this->json([
                'message' => 'Les données de performance sont invalides.',
                'errors' => [
                    'terrainType' => ['Le terrain doit correspondre à celui du plan d’entraînement.'],
                ],
            ], 422);
        }

        $performance = (new Performance())
            ->setSession($session)
            ->setUser($user)
            ->setDurationSec((int) $performanceData->durationSec)
            ->setDistanceKm((float) $performanceData->distanceKm)
            ->setElevationDPlus($performanceData->elevationDPlus)
            ->setAvgHr($performanceData->avgHr)
            ->setComment($performanceData->comment);
        $session->setStatus('completed');
        $adaptation = $adaptationService->adapt($performance);

        $entityManager->wrapInTransaction(function () use (
            $entityManager,
            $performance,
        ): void {
            $entityManager->persist($performance);
        });

        return $this->json([
            'id' => $performance->getId(),
            'sessionId' => $session->getId(),
            'distanceKm' => $performance->getDistanceKm(),
            'durationSec' => $performance->getDurationSec(),
            'elevationDPlus' => $performance->getElevationDPlus(),
            'avgHr' => $performance->getAvgHr(),
            'comment' => $performance->getComment(),
            'createdAt' => $performance->getCreatedAt()?->format(DATE_ATOM),
            'evaluation' => $adaptation->evaluation->toArray(),
            'adaptation' => $adaptation->toArray(),
        ], 201);
    }

    /** @param array<string, mixed> $data */
    private function dtoFromData(array $data): PerformanceDTO
    {
        $dto = new PerformanceDTO();
        $dto->sessionId = isset($data['sessionId']) ? (int) $data['sessionId'] : null;
        $dto->durationSec = isset($data['durationSec']) ? (int) $data['durationSec'] : null;
        $dto->distanceKm = isset($data['distanceKm']) ? (float) $data['distanceKm'] : null;
        $dto->elevationDPlus = isset($data['elevationDPlus']) ? (int) $data['elevationDPlus'] : null;
        $dto->terrainType = $data['terrainType'] ?? null;
        $dto->avgHr = isset($data['avgHr']) ? (int) $data['avgHr'] : null;
        $dto->comment = $data['comment'] ?? null;

        return $dto;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, list<string>>
     */
    private function typeErrors(array $data): array
    {
        $errors = [];

        foreach (['sessionId', 'durationSec', 'elevationDPlus', 'avgHr'] as $field) {
            if (isset($data[$field]) && !is_int($data[$field])) {
                $errors[$field][] = 'Cette valeur doit être un nombre entier.';
            }
        }

        if (isset($data['distanceKm']) && !is_int($data['distanceKm']) && !is_float($data['distanceKm'])) {
            $errors['distanceKm'][] = 'Cette valeur doit être un nombre.';
        }

        foreach (['terrainType', 'comment'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                $errors[$field][] = 'Cette valeur doit être une chaîne de caractères.';
            }
        }

        return $errors;
    }

    /** @return array<string, list<string>> */
    private function violationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }
}
