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