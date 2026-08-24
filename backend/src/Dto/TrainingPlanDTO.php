<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TrainingPlanDTO
{
    #[Assert\NotBlank(message: 'Choisissez un pôle de pratique.')]
    #[Assert\Choice(
        choices: ['discovery', 'intermediate', 'performance'],
        message: 'Le pôle de pratique est invalide.',
    )]
    public ?string $poleType = null;

    #[Assert\NotBlank(message: 'Choisissez un type d’objectif.')]
    #[Assert\Choice(choices: ['distance', 'time'], message: 'Le type d’objectif est invalide.')]
    public ?string $targetType = null;

    #[Assert\NotNull(message: 'Indiquez la valeur de votre objectif.')]
    #[Assert\Positive(message: 'La valeur de l’objectif doit être supérieure à zéro.')]
    public ?float $targetValue = null;

    #[Assert\NotBlank(message: 'Choisissez une unité.')]
    #[Assert\Choice(choices: ['km', 'm', 's', 'min'], message: 'L’unité sélectionnée est invalide.')]
    public ?string $targetUnit = null;

    #[Assert\NotBlank(message: 'Choisissez un type de terrain.')]
    #[Assert\Choice(choices: ['road', 'path', 'trail'], message: 'Le terrain sélectionné est invalide.')]
    public ?string $terrainType = null;

    #[Assert\PositiveOrZero(message: 'Le dénivelé ne peut pas être négatif.')]
    public ?int $elevationTargetDPlus = null;

    #[Assert\Callback]
    public function validateUnit(ExecutionContextInterface $context): void
    {
        $allowedUnits = match ($this->targetType) {
            'distance' => ['km', 'm'],
            'time' => ['s', 'min'],
            default => [],
        };

        if ($this->targetUnit !== null
            && $allowedUnits !== []
            && !in_array($this->targetUnit, $allowedUnits, true)) {
            $context->buildViolation('Cette unité ne correspond pas au type d’objectif choisi.')
                ->atPath('targetUnit')
                ->addViolation();
        }
    }

    /** @return array<string, float|int|string|null> */
    public function toArray(): array
    {
        return [
            'poleType' => $this->poleType,
            'targetType' => $this->targetType,
            'targetValue' => $this->targetValue,
            'targetUnit' => $this->targetUnit,
            'terrainType' => $this->terrainType,
            'elevationTargetDPlus' => $this->elevationTargetDPlus,
        ];
    }
}
