<?php

namespace App\Service;

use App\Dto\TrainingPlanDTO;
use App\Entity\Profile;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TrainingPlanGeneratorService
{
    public function __construct(
        private readonly AlgorithmParameterService $parameterService,
        private readonly ValidatorInterface $validator,
        private readonly FeasibilityService $feasibilityService,
        private readonly SessionGeneratorService $sessionGenerator,
    ) {
    }

    public function generatePlan(User $user, TrainingPlanDTO $data): TrainingPlan
    {
        $profile = $user->getProfile();

        if (!$profile instanceof Profile) {
            throw new \DomainException('Un profil physiologique est requis pour générer un plan.');
        }

        $violations = $this->validator->validate($data);

        if (count($violations) > 0) {
            throw new \InvalidArgumentException('L’objectif d’entraînement est invalide.');
        }

        $parameters = $this->parameterService->getCurrentParameters();
        $minimumWeeks = $this->positiveIntegerParameter($parameters, 'default_plan_min_weeks');
        $maximumWeeks = $this->positiveIntegerParameter($parameters, 'default_plan_max_weeks');
        $poleType = (string) $data->poleType;
        $sessionsPerWeek = $this->positiveIntegerParameter(
            $parameters,
            sprintf('max_sessions_%s', $poleType),
        );

        if ($minimumWeeks > $maximumWeeks) {
            throw new \LogicException('Les bornes de durée du plan sont incohérentes.');
        }

        $durationWeeks = max(
            $minimumWeeks,
            min($maximumWeeks, $this->recommendedDuration($data))
        );
        $startDate = new \DateTimeImmutable('monday next week');
        $endDate = $startDate->modify(sprintf('+%d weeks -1 day', $durationWeeks));

        $plan = (new TrainingPlan())
            ->setName($this->planName($data))
            ->setPoleType($poleType)
            ->setTargetType((string) $data->targetType)
            ->setTargetValue((float) $data->targetValue)
            ->setTargetUnit((string) $data->targetUnit)
            ->setTerrainType((string) $data->terrainType)
            ->setElevationTargetDPlus($data->elevationTargetDPlus)
            ->setFeasibilityIndicator('pending')
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setDurationWeeks($durationWeeks)
            ->setIsActive(true)
            ->setCurrentWeek(1)
            ->setProgressScore(0.0);

        $user->addTrainingPlan($plan);
        $plan->setFeasibilityIndicator(
            $this->feasibilityService->evaluate($profile, $plan)->value
        );
        $this->sessionGenerator->generate($plan, $profile);

        return $plan;
    }

    /** @param array<string, float> $parameters */
    private function positiveIntegerParameter(array $parameters, string $key): int
    {
        if (!isset($parameters[$key]) || $parameters[$key] <= 0) {
            throw new \LogicException(sprintf('Le paramètre algorithmique "%s" est manquant ou invalide.', $key));
        }

        return (int) round($parameters[$key]);
    }

    private function recommendedDuration(TrainingPlanDTO $data): int
    {
        $value = (float) $data->targetValue;

        if ($data->targetType === 'distance') {
            $distanceKm = $data->targetUnit === 'm' ? $value / 1000 : $value;

            return match (true) {
                $distanceKm <= 10 => 8,
                $distanceKm <= 21.1 => 12,
                default => 16,
            };
        }

        $durationMinutes = $data->targetUnit === 's' ? $value / 60 : $value;

        return match (true) {
            $durationMinutes <= 60 => 8,
            $durationMinutes <= 120 => 12,
            default => 16,
        };
    }

    private function planName(TrainingPlanDTO $data): string
    {
        return sprintf('Objectif %s %s', $data->targetValue, $data->targetUnit);
    }
}
