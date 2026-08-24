<?php

namespace App\Service;

use App\Dto\PerformanceEvaluation;
use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Repository\PerformanceRepository;

final class ProgressScoreCalculatorService
{
    private const float DISTANCE_WEIGHT = 0.30;
    private const float PACE_WEIGHT = 0.30;
    private const float REGULARITY_WEIGHT = 0.20;
    private const float EVOLUTION_WEIGHT = 0.10;
    private const float INTENSITY_WEIGHT = 0.10;

    public function __construct(private readonly PerformanceRepository $performanceRepository)
    {
    }

    public function calculate(
        Performance $currentPerformance,
        PerformanceEvaluation $currentEvaluation,
    ): float {
        $user = $currentPerformance->getUser();
        $currentSession = $currentPerformance->getSession();
        $plan = $currentSession?->getTrainingPlan();

        if ($user === null || $currentSession === null || $plan === null) {
            throw new \InvalidArgumentException('La performance doit être rattachée à un utilisateur, une séance et un plan.');
        }

        $history = array_values(array_filter(
            $this->performanceRepository->findUserPerformances($user),
            fn (Performance $performance): bool => $performance !== $currentPerformance
                && $this->belongsToPlan($performance, $plan),
        ));
        $rates = [$this->performanceRates($currentPerformance)];

        foreach ($history as $performance) {
            $rates[] = $this->performanceRates($performance);
        }

        $distanceScore = $this->average(array_column($rates, 'distance')) * 100;
        $paceScore = $this->average(array_column($rates, 'pace')) * 100;
        $regularityScore = $this->regularityScore($plan, $currentSession);
        $evolutionScore = $this->evolutionScore($currentPerformance, $history);
        $intensityScore = $currentEvaluation->targetIntensityRespected ? 100.0 : 60.0;
        $score = ($distanceScore * self::DISTANCE_WEIGHT)
            + ($paceScore * self::PACE_WEIGHT)
            + ($regularityScore * self::REGULARITY_WEIGHT)
            + ($evolutionScore * self::EVOLUTION_WEIGHT)
            + ($intensityScore * self::INTENSITY_WEIGHT);

        return round(max(0.0, min(100.0, $score)), 2);
    }

    /** @return array{distance: float, pace: float} */
    private function performanceRates(Performance $performance): array
    {
        $session = $performance->getSession();
        $plannedDistance = $session?->getPlannedDistanceKm();
        $plannedDuration = $session?->getPlannedDurationMin();
        $actualDistance = $performance->getDistanceKm();
        $actualDuration = $performance->getDurationSec();

        if ($plannedDistance === null || $plannedDistance <= 0
            || $plannedDuration === null || $plannedDuration <= 0
            || $actualDistance === null || $actualDistance <= 0
            || $actualDuration === null || $actualDuration <= 0) {
            throw new \InvalidArgumentException('Une performance historique contient des données incomplètes ou invalides.');
        }

        $distanceRate = min(1.0, $actualDistance / $plannedDistance);
        $plannedSpeed = $plannedDistance / ($plannedDuration / 60);
        $actualSpeed = $actualDistance / ($actualDuration / 3600);

        return [
            'distance' => $distanceRate,
            'pace' => min(1.0, $actualSpeed / $plannedSpeed),
        ];
    }

    private function regularityScore(TrainingPlan $plan, Session $currentSession): float
    {
        $currentDate = $currentSession->getDate();
        $dueSessions = array_filter(
            $plan->getSessions()->toArray(),
            static fn (Session $session): bool => $session === $currentSession
                || ($currentDate !== null && $session->getDate() !== null && $session->getDate() <= $currentDate),
        );

        if ($dueSessions === []) {
            return 100.0;
        }

        $completed = count(array_filter(
            $dueSessions,
            static fn (Session $session): bool => $session === $currentSession || $session->getStatus() === 'done',
        ));

        return $completed / count($dueSessions) * 100;
    }

    /** @param list<Performance> $history */
    private function evolutionScore(Performance $currentPerformance, array $history): float
    {
        if ($history === []) {
            return 100.0;
        }

        usort(
            $history,
            static fn (Performance $left, Performance $right): int =>
                ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0),
        );
        $currentSpeed = $this->actualSpeed($currentPerformance);
        $previousSpeed = $this->actualSpeed($history[0]);

        return min(100.0, $currentSpeed / $previousSpeed * 100);
    }

    private function actualSpeed(Performance $performance): float
    {
        $distance = $performance->getDistanceKm();
        $duration = $performance->getDurationSec();

        if ($distance === null || $distance <= 0 || $duration === null || $duration <= 0) {
            throw new \InvalidArgumentException('La vitesse ne peut pas être calculée avec ces valeurs.');
        }

        return $distance / ($duration / 3600);
    }

    private function belongsToPlan(Performance $performance, TrainingPlan $plan): bool
    {
        $candidate = $performance->getSession()?->getTrainingPlan();

        return $candidate === $plan
            || ($candidate?->getId() !== null && $candidate->getId() === $plan->getId());
    }

    /** @param list<float> $values */
    private function average(array $values): float
    {
        return array_sum($values) / count($values);
    }
}
