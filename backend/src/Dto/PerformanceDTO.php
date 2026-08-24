<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PerformanceDTO
{
    #[Assert\NotNull(message: 'L’identifiant de la séance est obligatoire.')]
    #[Assert\Positive(message: 'L’identifiant de la séance doit être positif.')]
    public ?int $sessionId = null;

    #[Assert\NotNull(message: 'Le temps réalisé est obligatoire.')]
    #[Assert\Positive(message: 'Le temps réalisé doit être supérieur à zéro.')]
    public ?int $durationSec = null;

    #[Assert\NotNull(message: 'La distance réalisée est obligatoire.')]
    #[Assert\Positive(message: 'La distance réalisée doit être supérieure à zéro.')]
    public ?float $distanceKm = null;

    #[Assert\PositiveOrZero(message: 'Le dénivelé ne peut pas être négatif.')]
    public ?int $elevationDPlus = null;

    #[Assert\NotBlank(message: 'Le terrain pratiqué est obligatoire.')]
    #[Assert\Choice(
        choices: ['road', 'trail'],
        message: 'Le terrain pratiqué est invalide.',
    )]
    public ?string $terrainType = null;

    #[Assert\Range(
        min: 30,
        max: 230,
        notInRangeMessage: 'La fréquence cardiaque moyenne doit être comprise entre {{ min }} et {{ max }} bpm.',
    )]
    public ?int $avgHr = null;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
    )]
    public ?string $comment = null;
}
