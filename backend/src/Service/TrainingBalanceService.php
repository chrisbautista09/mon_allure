<?php

namespace App\Service;

use App\Entity\TrainingPlan;

final class TrainingBalanceService
{
    private const REFERENCES = [
        'discovery' => ['endurance' => 100.0, 'threshold' => 0.0, 'vma' => 0.0],
        'intermediate' => ['endurance' => 66.67, 'threshold' => 33.33, 'vma' => 0.0],
        'performance' => ['endurance' => 60.0, 'threshold' => 20.0, 'vma' => 20.0],
    ];

    private const TOLERANCE = 10.0;

    public function __construct(
        private readonly ZoneDistributionService $zoneDistributionService,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     actual: array{endurance: float, threshold: float, vma: float},
     *     reference: array{endurance: float, threshold: float, vma: float}|null
     * }
     */
    public function analyze(
        TrainingPlan $plan,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        $reference = self::REFERENCES[$plan->getPoleType() ?? ''] ?? null;
        $statistics = $this->zoneDistributionService->getPlanStatistics($plan, $start, $end);
        $actual = $this->groupZones($statistics['zones']);

        if ($reference === null || $statistics['totalSessions'] === 0) {
            return [
                'status' => 'unavailable',
                'message' => 'Ajoutez des séances avec leurs zones pour analyser l’équilibre du plan.',
                'actual' => $actual,
                'reference' => $reference,
            ];
        }

        $intensive = $actual['threshold'] + $actual['vma'];
        $referenceIntensive = $reference['threshold'] + $reference['vma'];

        if ($intensive > $referenceIntensive + self::TOLERANCE) {
            return $this->result('too_intensive', 'Trop de séances intensives dans ce plan.', $actual, $reference);
        }

        if ($actual['threshold'] < max(0.0, $reference['threshold'] - self::TOLERANCE)) {
            return $this->result('threshold_deficit', 'Le plan manque de travail au seuil.', $actual, $reference);
        }

        if ($actual['endurance'] < max(0.0, $reference['endurance'] - self::TOLERANCE)) {
            return $this->result('endurance_deficit', 'Le plan manque de séances en endurance.', $actual, $reference);
        }

        return $this->result('balanced', 'La répartition de votre plan est équilibrée.', $actual, $reference);
    }

    /**
     * @param list<array{zone: string, sessions: int, percentage: float}> $zones
     *
     * @return array{endurance: float, threshold: float, vma: float}
     */
    private function groupZones(array $zones): array
    {
        $groups = ['endurance' => 0.0, 'threshold' => 0.0, 'vma' => 0.0];

        foreach ($zones as $zone) {
            $group = match ($zone['zone']) {
                'Z1', 'Z2', 'Z3' => 'endurance',
                'Z4' => 'threshold',
                'Z5' => 'vma',
                default => null,
            };

            if ($group !== null) {
                $groups[$group] += $zone['percentage'];
            }
        }

        return array_map(static fn (float $value): float => round($value, 2), $groups);
    }

    /**
     * @param array{endurance: float, threshold: float, vma: float} $actual
     * @param array{endurance: float, threshold: float, vma: float} $reference
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     actual: array{endurance: float, threshold: float, vma: float},
     *     reference: array{endurance: float, threshold: float, vma: float}
     * }
     */
    private function result(string $status, string $message, array $actual, array $reference): array
    {
        return compact('status', 'message', 'actual', 'reference');
    }
}
