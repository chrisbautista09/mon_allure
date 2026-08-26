<?php

namespace App\Service;

use App\Dto\FormStatus;
use App\Entity\Performance;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\FormStatusLevel;
use App\Enum\FormStatusDataState;
use App\Enum\FormTrend;
use App\Repository\PerformanceRepository;
use Symfony\Component\Clock\ClockInterface;

final class FormStatusService
{
    private const int RECENT_DAYS = 21;
    private const int RECENT_PERFORMANCE_LIMIT = 10;
    private const float PLAN_WEIGHT = 0.30;
    private const float RECENT_WEIGHT = 0.70;
    private const float TREND_THRESHOLD = 5.0;
    private const int TREND_SAMPLE_SIZE = 3;
    private const int RELIABLE_PERFORMANCE_COUNT = 6;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly PerformanceRepository $performanceRepository,
    ) {
    }

    public function calculate(User $user): FormStatus
    {
        $calculatedAt = \DateTimeImmutable::createFromInterface($this->clock->now());
        $plan = $this->activePlan($user, $calculatedAt);
        $isCompleted = false;

        if ($plan === null) {
            $plan = $this->latestCompletedPlan($user, $calculatedAt);
            $isCompleted = $plan !== null;
        }

        if ($plan === null) {
            return new FormStatus(
                null,
                null,
                FormTrend::UNKNOWN,
                FormStatusDataState::NO_ACTIVE_PLAN,
                0,
                $calculatedAt,
            );
        }

        $performances = $this->performanceRepository->findRecentPerformances(
            $user,
            self::RECENT_PERFORMANCE_LIMIT,
        );
        $performanceScores = $this->recentPerformanceScores($performances, $plan, $calculatedAt);

        if ($performances === [] && !$isCompleted) {
            return new FormStatus(
                null,
                null,
                FormTrend::UNKNOWN,
                FormStatusDataState::NO_PERFORMANCE,
                0,
                $calculatedAt,
            );
        }

        $progressScore = $this->bound($plan->getProgressScore());
        $formScore = $performanceScores === []
            ? $progressScore
            : ($progressScore * self::PLAN_WEIGHT)
                + ($this->weightedAverage($performanceScores) * self::RECENT_WEIGHT);
        $score = (int) round($this->bound($formScore));

        return new FormStatus(
            $score,
            FormStatusLevel::fromScore($score),
            $this->trend($performanceScores),
            match (true) {
                $isCompleted => FormStatusDataState::PLAN_COMPLETED,
                count($performanceScores) < self::RELIABLE_PERFORMANCE_COUNT => FormStatusDataState::LIMITED_DATA,
                default => FormStatusDataState::READY,
            },
            count($performanceScores),
            $calculatedAt,
        );
    }

    private function activePlan(
        User $user,
        \DateTimeImmutable $calculatedAt,
    ): ?TrainingPlan {
        $activePlans = array_values(array_filter(
            $user->getTrainingPlans()->toArray(),
            static fn (TrainingPlan $plan): bool => $plan->isActive()
                && ($plan->getEndDate() === null || $plan->getEndDate() >= $calculatedAt),
        ));

        usort($activePlans, static function (TrainingPlan $left, TrainingPlan $right): int {
            $dateComparison = ($right->getStartDate()?->getTimestamp() ?? 0)
                <=> ($left->getStartDate()?->getTimestamp() ?? 0);

            return $dateComparison !== 0
                ? $dateComparison
                : ($right->getId() ?? 0) <=> ($left->getId() ?? 0);
        });

        return $activePlans[0] ?? null;
    }

    private function latestCompletedPlan(
        User $user,
        \DateTimeImmutable $calculatedAt,
    ): ?TrainingPlan {
        $completedPlans = array_values(array_filter(
            $user->getTrainingPlans()->toArray(),
            static fn (TrainingPlan $plan): bool => $plan->getEndDate() !== null
                && $plan->getEndDate() < $calculatedAt,
        ));
        usort(
            $completedPlans,
            static fn (TrainingPlan $left, TrainingPlan $right): int =>
                ($right->getEndDate()?->getTimestamp() ?? 0)
                <=> ($left->getEndDate()?->getTimestamp() ?? 0),
        );

        return $completedPlans[0] ?? null;
    }

    /**
     * @param list<Performance> $performances
     *
     * @return list<float> Scores ordered from newest to oldest.
     */
    private function recentPerformanceScores(
        array $performances,
        TrainingPlan $plan,
        \DateTimeImmutable $calculatedAt,
    ): array {
        $from = $calculatedAt->modify(sprintf('-%d days', self::RECENT_DAYS));
        $performances = array_filter(
            $performances,
            function (Performance $performance) use ($plan, $from, $calculatedAt): bool {
                $createdAt = $performance->getCreatedAt();

                return $this->belongsToPlan($performance, $plan)
                    && $createdAt !== null
                    && $createdAt >= $from
                    && $createdAt <= $calculatedAt;
            },
        );
        usort(
            $performances,
            static fn (Performance $left, Performance $right): int =>
                ($right->getCreatedAt()?->getTimestamp() ?? 0)
                <=> ($left->getCreatedAt()?->getTimestamp() ?? 0),
        );

        $scores = [];

        foreach ($performances as $performance) {
            $score = $this->performanceScore($performance);

            if ($score !== null) {
                $scores[] = $score;
            }
        }

        return $scores;
    }

    private function performanceScore(Performance $performance): ?float
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
            return null;
        }

        $distanceRatio = $actualDistance / $plannedDistance;
        $plannedSpeed = $plannedDistance / ($plannedDuration / 60);
        $actualSpeed = $actualDistance / ($actualDuration / 3600);

        return min($distanceRatio, $actualSpeed / $plannedSpeed, 1.0) * 100;
    }

    /** @param list<float> $scores */
    private function trend(array $scores): FormTrend
    {
        $recent = array_slice($scores, 0, self::TREND_SAMPLE_SIZE);
        $previous = array_slice($scores, self::TREND_SAMPLE_SIZE, self::TREND_SAMPLE_SIZE);

        if ($recent === [] || $previous === []) {
            return FormTrend::UNKNOWN;
        }

        $difference = $this->average($recent) - $this->average($previous);

        return match (true) {
            $difference >= self::TREND_THRESHOLD => FormTrend::IMPROVING,
            $difference <= -self::TREND_THRESHOLD => FormTrend::DECLINING,
            default => FormTrend::STABLE,
        };
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

    /**
     * The scores are ordered from newest to oldest. The newest score receives
     * the highest linear weight and the oldest receives a weight of one.
     *
     * @param list<float> $values
     */
    private function weightedAverage(array $values): float
    {
        $weightedSum = 0.0;
        $weightSum = 0;
        $weight = count($values);

        foreach ($values as $value) {
            $weightedSum += $value * $weight;
            $weightSum += $weight;
            --$weight;
        }

        return $weightedSum / $weightSum;
    }

    private function bound(float $score): float
    {
        return max(0.0, min(100.0, $score));
    }
}
