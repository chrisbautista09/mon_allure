<?php

namespace App\Service;

use App\Entity\Performance;
use App\Entity\User;
use App\Repository\PerformanceRepository;

final class PerformanceStatisticsService
{
    public function __construct(
        private readonly PerformanceRepository $performanceRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHistory(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        $history = [];

        foreach ($this->performances($user, $start, $end) as $performance) {
            $date = $performance->getSession()?->getDate();

            if ($date === null) {
                continue;
            }

            $history[] = [
                'date' => $date->format('Y-m-d'),
                'distance' => (float) $performance->getDistanceKm(),
                'time' => (int) $performance->getDurationSec(),
                'elevation' => $performance->getElevationDPlus(),
                'planned' => [
                    'distance' => $performance->getSession()?->getPlannedDistanceKm(),
                    'time' => $performance->getSession()?->getPlannedDurationMin() === null
                        ? null
                        : $performance->getSession()?->getPlannedDurationMin() * 60,
                    'elevation' => $performance->getSession()?->getPlannedElevationDPlus(),
                ],
                'comparison' => [
                    'distance' => $this->volumeComparison(
                        $performance->getDistanceKm(),
                        $performance->getSession()?->getPlannedDistanceKm(),
                    ),
                    'time' => $this->durationComparison(
                        $performance->getDurationSec(),
                        $performance->getSession()?->getPlannedDurationMin() === null
                            ? null
                            : $performance->getSession()?->getPlannedDurationMin() * 60,
                    ),
                    'elevation' => $this->volumeComparison(
                        $performance->getElevationDPlus(),
                        $performance->getSession()?->getPlannedElevationDPlus(),
                    ),
                ],
            ];
        }

        return $history;
    }

    /** @return list<array{date: string, value: float}> */
    public function getDistanceEvolution(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        return $this->evolution($this->performances($user, $start, $end), 'distance');
    }

    /** @return list<array{date: string, value: int}> */
    public function getTimeEvolution(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        return $this->evolution($this->performances($user, $start, $end), 'time');
    }

    /** @return list<array{date: string, value: int}> */
    public function getElevationEvolution(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        return $this->evolution($this->performances($user, $start, $end), 'elevation');
    }

    /**
     * @return array{
     *     performanceCount: int,
     *     distance: array{average: float|null, best: float|null, worst: float|null, progression: float|null},
     *     time: array{average: float|null, best: int|null, worst: int|null, progression: float|null},
     *     elevation: array{average: float|null, best: int|null, worst: int|null, progression: float|null}
     * }
     */
    public function getGlobalStatistics(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        $performances = $this->performances($user, $start, $end);

        return [
            'performanceCount' => count($performances),
            'distance' => $this->metricStatistics($performances, 'distance'),
            'time' => $this->metricStatistics($performances, 'time'),
            'elevation' => $this->metricStatistics($performances, 'elevation'),
        ];
    }

    /**
     * @return array{
     *     totalDistance: float,
     *     totalTimeSec: int,
     *     totalElevation: int,
     *     sessionCount: int,
     *     averageDistance: float,
     *     averageTimeSec: int
     * }
     */
    public function getSummaryStatistics(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array {
        $performances = $this->performances($user, $start, $end);
        $sessionCount = count($performances);
        $totalDistance = 0.0;
        $totalTimeSec = 0;
        $totalElevation = 0;

        foreach ($performances as $performance) {
            $totalDistance += (float) $performance->getDistanceKm();
            $totalTimeSec += (int) $performance->getDurationSec();
            $totalElevation += $performance->getElevationDPlus() ?? 0;
        }

        return [
            'totalDistance' => round($totalDistance, 2),
            'totalTimeSec' => $totalTimeSec,
            'totalElevation' => $totalElevation,
            'sessionCount' => $sessionCount,
            'averageDistance' => $sessionCount === 0 ? 0.0 : round($totalDistance / $sessionCount, 2),
            'averageTimeSec' => $sessionCount === 0 ? 0 : (int) round($totalTimeSec / $sessionCount),
        ];
    }

    /** @return list<Performance> */
    private function performances(
        User $user,
        ?\DateTimeInterface $start,
        ?\DateTimeInterface $end,
    ): array {
        if (($start === null) !== ($end === null)) {
            throw new \InvalidArgumentException('Les deux bornes de la période sont obligatoires.');
        }

        return $start !== null && $end !== null
            ? $this->performanceRepository->findByPeriod($user, $start, $end)
            : $this->performanceRepository->findByUser($user);
    }

    /**
     * @param list<Performance> $performances
     *
     * @return list<array{date: string, value: float|int}>
     */
    private function evolution(array $performances, string $metric): array
    {
        $evolution = [];

        foreach ($performances as $performance) {
            $date = $performance->getSession()?->getDate();
            $value = $this->metricValue($performance, $metric);

            if ($date !== null && $value !== null) {
                $evolution[] = [
                    'date' => $date->format('Y-m-d'),
                    'value' => $value,
                ];
            }
        }

        return $evolution;
    }

    /**
     * @param list<Performance> $performances
     *
     * @return array{average: float|null, best: float|int|null, worst: float|int|null, progression: float|null}
     */
    private function metricStatistics(array $performances, string $metric): array
    {
        $values = [];

        foreach ($performances as $performance) {
            $value = $this->metricValue($performance, $metric);

            if ($value !== null) {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return ['average' => null, 'best' => null, 'worst' => null, 'progression' => null];
        }

        $isTime = $metric === 'time';

        return [
            'average' => round(array_sum($values) / count($values), 2),
            'best' => $isTime ? min($values) : max($values),
            'worst' => $isTime ? max($values) : min($values),
            'progression' => $this->progression($values, $isTime),
        ];
    }

    private function metricValue(Performance $performance, string $metric): float|int|null
    {
        return match ($metric) {
            'distance' => $performance->getDistanceKm(),
            'time' => $performance->getDurationSec(),
            'elevation' => $performance->getElevationDPlus(),
            default => throw new \LogicException('Métrique de performance inconnue.'),
        };
    }

    /** @return array{difference: float|int|null, percentage: float|null, status: string} */
    private function volumeComparison(float|int|null $actual, float|int|null $planned): array
    {
        if ($actual === null || $planned === null || $planned <= 0) {
            return ['difference' => null, 'percentage' => null, 'status' => 'unavailable'];
        }

        $percentage = $actual / $planned * 100;

        return [
            'difference' => round($actual - $planned, 2),
            'percentage' => round($percentage, 2),
            'status' => match (true) {
                $percentage >= 100 => 'attained',
                $percentage >= 90 => 'close',
                default => 'not_attained',
            },
        ];
    }

    /** @return array{difference: int|null, percentage: float|null, status: string} */
    private function durationComparison(?int $actual, ?int $planned): array
    {
        if ($actual === null || $planned === null || $planned <= 0) {
            return ['difference' => null, 'percentage' => null, 'status' => 'unavailable'];
        }

        $deviationPercentage = abs($actual - $planned) / $planned * 100;

        return [
            'difference' => $actual - $planned,
            'percentage' => round($actual / $planned * 100, 2),
            'status' => match (true) {
                $deviationPercentage <= 10 => 'attained',
                $deviationPercentage <= 20 => 'close',
                default => 'not_attained',
            },
        ];
    }

    /** @param list<float|int> $values */
    private function progression(array $values, bool $lowerIsBetter): ?float
    {
        if (count($values) < 2 || $values[0] == 0) {
            return null;
        }

        $first = $values[0];
        $last = $values[array_key_last($values)];
        $difference = $lowerIsBetter ? $first - $last : $last - $first;

        return round(($difference / $first) * 100, 2);
    }
}
