<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\TrainingPlan;
use App\Enum\FeasibilityLevel;

class FeasibilityService
{
    public function evaluate(
        Profile $profile,
        TrainingPlan $plan,
        int $minimumWeeks,
    ): FeasibilityLevel {
        if ($minimumWeeks <= 0) {
            throw new \InvalidArgumentException('La durée minimale doit être supérieure à zéro.');
        }

        $durationWeeks = $plan->getDurationWeeks();

        if ($durationWeeks === null || $durationWeeks < $minimumWeeks) {
            return FeasibilityLevel::LOW;
        }

        $fitnessScore = $this->fitnessScore($profile);
        $difficultyScore = $this->difficultyScore($plan);
        $preparationBonus = match (true) {
            $durationWeeks >= $minimumWeeks + 8 => 1,
            $durationWeeks < $minimumWeeks + 4 => -1,
            default => 0,
        };

        return match (true) {
            $fitnessScore - $difficultyScore + $preparationBonus <= -2 => FeasibilityLevel::LOW,
            $fitnessScore - $difficultyScore + $preparationBonus === -1 => FeasibilityLevel::MEDIUM,
            $fitnessScore - $difficultyScore + $preparationBonus <= 1 => FeasibilityLevel::GOOD,
            default => FeasibilityLevel::OPTIMAL,
        };
    }

    private function fitnessScore(Profile $profile): int
    {
        $scores = [];

        if ($profile->getVma() !== null) {
            $scores[] = match (true) {
                $profile->getVma() < 10 => 0,
                $profile->getVma() < 13 => 1,
                $profile->getVma() < 16 => 2,
                default => 3,
            };
        }

        if ($profile->getVo2max() !== null) {
            $scores[] = match (true) {
                $profile->getVo2max() < 35 => 0,
                $profile->getVo2max() < 45 => 1,
                $profile->getVo2max() < 55 => 2,
                default => 3,
            };
        }

        if ($scores === []) {
            return 0;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    private function difficultyScore(TrainingPlan $plan): int
    {
        $targetValue = $plan->getTargetValue() ?? 0.0;

        if ($plan->getTargetType() === 'distance') {
            $distanceKm = $plan->getTargetUnit() === 'm' ? $targetValue / 1000 : $targetValue;

            return match (true) {
                $distanceKm <= 10 => 0,
                $distanceKm <= 21.1 => 1,
                $distanceKm <= 42.2 => 2,
                default => 3,
            };
        }

        $durationMinutes = $plan->getTargetUnit() === 's' ? $targetValue / 60 : $targetValue;

        return match (true) {
            $durationMinutes <= 60 => 0,
            $durationMinutes <= 120 => 1,
            $durationMinutes <= 240 => 2,
            default => 3,
        };
    }
}
