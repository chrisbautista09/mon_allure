<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\SessionIntensityZoneRepository;

final class ZoneDistributionService
{
    public function __construct(
        private readonly SessionIntensityZoneRepository $sessionIntensityZoneRepository,
    ) {
    }

    /** @return list<array{zone: string, sessions: int, percentage: float}> */
    public function calculateDistribution(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        return $this->calculate(
            $this->sessionIntensityZoneRepository->findDistributionByUser($user, $start, $end),
        );
    }

    /** @return list<array{zone: string, sessions: int, percentage: float}> */
    public function calculatePlanDistribution(
        TrainingPlan $plan,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        return $this->calculate(
            $this->sessionIntensityZoneRepository->findDistributionByTrainingPlan($plan, $start, $end),
        );
    }

    /**
     * @return array{
     *     totalSessions: int,
     *     dominantZone: string|null,
     *     zones: list<array{zone: string, sessions: int, percentage: float}>
     * }
     */
    public function getStatistics(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        return $this->statistics(
            $this->calculateDistribution($user, $start, $end),
            $this->sessionIntensityZoneRepository->countDistinctSessionsByUser($user, $start, $end),
        );
    }

    /**
     * @return array{
     *     totalSessions: int,
     *     dominantZone: string|null,
     *     zones: list<array{zone: string, sessions: int, percentage: float}>
     * }
     */
    public function getPlanStatistics(
        TrainingPlan $plan,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        return $this->statistics(
            $this->calculatePlanDistribution($plan, $start, $end),
            $this->sessionIntensityZoneRepository->countDistinctSessionsByTrainingPlan($plan, $start, $end),
        );
    }

    /**
     * @param list<array{
     *     zoneId: int,
     *     name: string,
     *     occurrenceCount: int,
     *     durationPercentTotal: float
     * }> $rows
     *
     * @return list<array{zone: string, sessions: int, percentage: float}>
     */
    private function calculate(array $rows): array
    {
        $rows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => in_array($row['name'] ?? null, ['Z1', 'Z2', 'Z3', 'Z4', 'Z5'], true)
                && isset($row['occurrenceCount'])
                && is_numeric($row['occurrenceCount'])
                && (int) $row['occurrenceCount'] > 0,
        ));
        $rows = array_map(static function (array $row): array {
            $row['occurrenceCount'] = (int) $row['occurrenceCount'];

            return $row;
        }, $rows);
        $totalOccurrences = array_sum(array_column($rows, 'occurrenceCount'));

        if ($totalOccurrences <= 0) {
            return [];
        }

        $distribution = [];
        $allocatedPercentage = 0.0;
        $lastIndex = array_key_last($rows);

        foreach ($rows as $index => $row) {
            $percentage = $index === $lastIndex
                ? round(100.0 - $allocatedPercentage, 2)
                : round($row['occurrenceCount'] / $totalOccurrences * 100, 2);

            $distribution[] = [
                'zone' => $row['name'],
                'sessions' => $row['occurrenceCount'],
                'percentage' => $percentage,
            ];
            $allocatedPercentage += $percentage;
        }

        return $distribution;
    }

    /**
     * @param list<array{zone: string, sessions: int, percentage: float}> $distribution
     *
     * @return array{
     *     totalSessions: int,
     *     dominantZone: string|null,
     *     zones: list<array{zone: string, sessions: int, percentage: float}>
     * }
     */
    private function statistics(array $distribution, int $totalSessions): array
    {
        $dominantZone = null;
        $dominantOccurrences = -1;

        foreach ($distribution as $zone) {
            if ($zone['sessions'] > $dominantOccurrences) {
                $dominantOccurrences = $zone['sessions'];
                $dominantZone = $zone['zone'];
            }
        }

        return [
            'totalSessions' => $totalSessions,
            'dominantZone' => $dominantZone,
            'zones' => $distribution,
        ];
    }
}
