<?php

namespace App\Entity;

use App\Repository\TrainingPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingPlanRepository::class)]
class TrainingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $pole_type = null;

    #[ORM\Column(length: 255)]
    private ?string $target_type = null;

    #[ORM\Column]
    private ?float $target_value = null;

    #[ORM\Column(length: 255)]
    private ?string $target_unit = null;

    #[ORM\Column(length: 255)]
    private ?string $terrain_type = null;

    #[ORM\Column(nullable: true)]
    private ?int $elevation_target = null;

    #[ORM\Column(length: 255)]
    private ?string $feasibility_indicator = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $start_date = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $end_date = null;

    #[ORM\Column]
    private ?int $duration_weeks = null;

    #[ORM\Column]
    private ?bool $is_active = null;

    #[ORM\Column]
    private ?int $current_week = null;

    #[ORM\Column]
    private ?float $progress_score = null;

    #[ORM\ManyToOne(inversedBy: 'trainingPlans')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPoleType(): ?string
    {
        return $this->pole_type;
    }

    public function setPoleType(string $pole_type): static
    {
        $this->pole_type = $pole_type;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->target_type;
    }

    public function setTargetType(string $target_type): static
    {
        $this->target_type = $target_type;

        return $this;
    }

    public function getTargetValue(): ?float
    {
        return $this->target_value;
    }

    public function setTargetValue(float $target_value): static
    {
        $this->target_value = $target_value;

        return $this;
    }

    public function getTargetUnit(): ?string
    {
        return $this->target_unit;
    }

    public function setTargetUnit(string $target_unit): static
    {
        $this->target_unit = $target_unit;

        return $this;
    }

    public function getTerrainType(): ?string
    {
        return $this->terrain_type;
    }

    public function setTerrainType(string $terrain_type): static
    {
        $this->terrain_type = $terrain_type;

        return $this;
    }

    public function getElevationTarget(): ?int
    {
        return $this->elevation_target;
    }

    public function setElevationTarget(?int $elevation_target): static
    {
        $this->elevation_target = $elevation_target;

        return $this;
    }

    public function getFeasibilityIndicator(): ?string
    {
        return $this->feasibility_indicator;
    }

    public function setFeasibilityIndicator(string $feasibility_indicator): static
    {
        $this->feasibility_indicator = $feasibility_indicator;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function setEndDate(\DateTime $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function getDurationWeeks(): ?int
    {
        return $this->duration_weeks;
    }

    public function setDurationWeeks(int $duration_weeks): static
    {
        $this->duration_weeks = $duration_weeks;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getCurrentWeek(): ?int
    {
        return $this->current_week;
    }

    public function setCurrentWeek(int $current_week): static
    {
        $this->current_week = $current_week;

        return $this;
    }

    public function getProgressScore(): ?float
    {
        return $this->progress_score;
    }

    public function setProgressScore(float $progress_score): static
    {
        $this->progress_score = $progress_score;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
