<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use Symfony\Component\Clock\ClockInterface;

final class ProgressService
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function synchronizeCurrentWeek(TrainingPlan $plan): bool
    {
        $currentWeek = $this->calculateCurrentWeek($plan);

        if ($currentWeek === $plan->getCurrentWeek()) {
            return false;
        }

        $plan->setCurrentWeek($currentWeek);

        return true;
    }

    public function calculateCurrentWeek(TrainingPlan $plan): int
    {
        $startDate = $plan->getStartDate();
        $durationWeeks = $plan->getDurationWeeks();

        if ($startDate === null || $durationWeeks === null || $durationWeeks <= 0) {
            return $plan->getCurrentWeek();
        }

        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $startDate = $startDate->setTime(0, 0);

        if ($today < $startDate) {
            return 1;
        }

        $elapsedDays = (int) $startDate->diff($today)->days;

        return min($durationWeeks, intdiv($elapsedDays, 7) + 1);
    }

    public function isPlanCompleted(TrainingPlan $plan): bool
    {
        $endDate = $plan->getEndDate();

        if ($endDate === null) {
            return false;
        }

        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);

        return $today > $endDate->setTime(0, 0);
    }

    public function calculatePlanProgress(TrainingPlan $plan): int
    {
        return $plan->getProgressPercentage();
    }

    public function getSportsProgress(TrainingPlan $plan): int
    {
        return (int) round(min(100, max(0, $plan->getProgressScore())));
    }

    public function getCurrentPhase(TrainingPlan $plan): string
    {
        $progress = $this->calculatePlanProgress($plan);

        return match (true) {
            $progress <= 25 => 'Mise en condition',
            $progress <= 70 => 'Développement endurance',
            $progress <= 90 => 'Développement spécifique',
            default => 'Affûtage et objectif',
        };
    }
}
