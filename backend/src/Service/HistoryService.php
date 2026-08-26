<?php

namespace App\Service;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\SessionRepository;
use Symfony\Component\Clock\ClockInterface;

final class HistoryService
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     pagination: array{page: int, perPage: int, totalItems: int, totalPages: int}
     * }
     */
    public function getSessionHistory(
        User $user,
        int $page = 1,
        int $perPage = 20,
        ?string $status = null,
        ?string $period = null,
        ?string $search = null,
    ): array {
        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $fromDate = match ($period) {
            '30_days' => $today->modify('-29 days'),
            '3_months' => $today->modify('-3 months +1 day'),
            'year' => $today->modify('-1 year +1 day'),
            default => null,
        };
        $paginator = $this->sessionRepository->findPastSessionsByUser(
            $user,
            $page,
            $perPage,
            $today,
            $status,
            $fromDate,
            $search,
        );
        $totalItems = count($paginator);
        $items = [];

        foreach ($paginator as $session) {
            $items[] = $this->formatSession($session);
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems === 0 ? 0 : (int) ceil($totalItems / $perPage),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formatSession(Session $session): array
    {
        $plan = $session->getTrainingPlan();

        return [
            'id' => $session->getId(),
            'date' => $session->getDate()?->format('Y-m-d'),
            'title' => $session->getTitle(),
            'status' => strtoupper($session->getStatus()),
            'instructions' => $session->getInstructions(),
            'type' => $session->getSessionType(),
            'plannedDistanceKm' => $session->getPlannedDistanceKm(),
            'plannedDurationMin' => $session->getPlannedDurationMin(),
            'plannedElevationDPlus' => $session->getPlannedElevationDPlus(),
            'plan' => [
                'id' => $plan?->getId(),
                'name' => $plan?->getName(),
            ],
            'performance' => $this->formatPerformance(
                $session->getPerformance(),
                $plan?->getTerrainType(),
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function formatPerformance(?Performance $performance, ?string $terrainType): ?array
    {
        if ($performance === null) {
            return null;
        }

        return [
            'id' => $performance->getId(),
            'distanceKm' => $performance->getDistanceKm(),
            'durationSec' => $performance->getDurationSec(),
            'elevationDPlus' => $performance->getElevationDPlus(),
            'terrainType' => $terrainType,
            'avgHr' => $performance->getAvgHr(),
            'comment' => $performance->getComment(),
            'recordedAt' => $performance->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
