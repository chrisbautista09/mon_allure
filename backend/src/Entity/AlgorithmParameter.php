<?php

namespace App\Entity;

use App\Repository\AlgorithmParameterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlgorithmParameterRepository::class)]
class AlgorithmParameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Exemple :
     * progression_max_percent
     * coef_endurance
     * max_sessions_discovery
     */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $parameterKey = null;

    /**
     * Valeur numérique du paramètre.
     *
     * Exemples :
     * 10
     * 0.70
     * 2
     */
    #[ORM\Column]
    private ?float $parameterValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParameterKey(): ?string
    {
        return $this->parameterKey;
    }

    public function setParameterKey(string $parameterKey): static
    {
        $this->parameterKey = strtolower(trim($parameterKey));

        return $this;
    }

    public function getParameterValue(): ?float
    {
        return $this->parameterValue;
    }

    public function getSuccessValidationRate(): float
    {
        if ($this->parameterKey !== 'success_validation_rate' || $this->parameterValue === null) {
            throw new \LogicException('Le paramètre attendu est "success_validation_rate".');
        }

        if ($this->parameterValue < 0 || $this->parameterValue > 100) {
            throw new \LogicException('Le taux de validation doit être compris entre 0 et 100.');
        }

        return $this->parameterValue;
    }

    public function getProgressionMaxPercent(): float
    {
        if ($this->parameterKey !== 'progression_max_percent' || $this->parameterValue === null) {
            throw new \LogicException('Le paramètre attendu est "progression_max_percent".');
        }

        if ($this->parameterValue < 0 || $this->parameterValue > 10) {
            throw new \LogicException('La progression maximale doit être comprise entre 0 et 10 %.');
        }

        return $this->parameterValue;
    }

    public function getRecoveryWeekFrequency(): int
    {
        if ($this->parameterKey !== 'recovery_week_frequency' || $this->parameterValue === null) {
            throw new \LogicException('Le paramètre attendu est "recovery_week_frequency".');
        }

        if ($this->parameterValue < 1 || floor($this->parameterValue) !== $this->parameterValue) {
            throw new \LogicException('La fréquence des semaines de récupération doit être un entier positif.');
        }

        return (int) $this->parameterValue;
    }

    public function setParameterValue(float $parameterValue): static
    {
        $this->parameterValue = $parameterValue;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description !== null
            ? trim($description)
            : null;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(
        \DateTimeImmutable $updatedAt
    ): static {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updateValue(float $parameterValue): static
    {
        $this->parameterValue = $parameterValue;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
