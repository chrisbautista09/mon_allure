<?php

namespace App\Entity;

use App\Repository\AlgorithmParameterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlgorithmParameterRepository::class)]
class AlgorithmParameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $progression_max_percent = null;

    #[ORM\Column]
    private ?int $recovery_week_frequency = null;

    #[ORM\Column]
    private ?int $max_sessions_discovery = null;

    #[ORM\Column]
    private ?int $max_sessions_intermediate = null;

    #[ORM\Column]
    private ?int $max_sessions_performance = null;

    #[ORM\Column]
    private ?float $long_run_ratio_max = null;

    #[ORM\Column]
    private ?float $coef_endurance = null;

    #[ORM\Column]
    private ?float $coef_active = null;

    #[ORM\Column]
    private ?float $coef_threshold = null;

    #[ORM\Column]
    private ?float $coef_vma = null;

    #[ORM\Column]
    private ?int $default_plan_min_weeks = null;

    #[ORM\Column]
    private ?int $defautl_plan_max_weeks = null;

    #[ORM\Column]
    private ?int $missed_session_tolerance = null;

    #[ORM\Column]
    private ?float $succes_validation_rate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProgressionMaxPercent(): ?float
    {
        return $this->progression_max_percent;
    }

    public function setProgressionMaxPercent(float $progression_max_percent): static
    {
        $this->progression_max_percent = $progression_max_percent;

        return $this;
    }

    public function getRecoveryWeekFrequency(): ?int
    {
        return $this->recovery_week_frequency;
    }

    public function setRecoveryWeekFrequency(int $recovery_week_frequency): static
    {
        $this->recovery_week_frequency = $recovery_week_frequency;

        return $this;
    }

    public function getMaxSessionsDiscovery(): ?int
    {
        return $this->max_sessions_discovery;
    }

    public function setMaxSessionsDiscovery(int $max_sessions_discovery): static
    {
        $this->max_sessions_discovery = $max_sessions_discovery;

        return $this;
    }

    public function getMaxSessionsIntermediate(): ?int
    {
        return $this->max_sessions_intermediate;
    }

    public function setMaxSessionsIntermediate(int $max_sessions_intermediate): static
    {
        $this->max_sessions_intermediate = $max_sessions_intermediate;

        return $this;
    }

    public function getMaxSessionsPerformance(): ?int
    {
        return $this->max_sessions_performance;
    }

    public function setMaxSessionsPerformance(int $max_sessions_performance): static
    {
        $this->max_sessions_performance = $max_sessions_performance;

        return $this;
    }

    public function getLongRunRatioMax(): ?float
    {
        return $this->long_run_ratio_max;
    }

    public function setLongRunRatioMax(float $long_run_ratio_max): static
    {
        $this->long_run_ratio_max = $long_run_ratio_max;

        return $this;
    }

    public function getCoefEndurance(): ?float
    {
        return $this->coef_endurance;
    }

    public function setCoefEndurance(float $coef_endurance): static
    {
        $this->coef_endurance = $coef_endurance;

        return $this;
    }

    public function getCoefActive(): ?float
    {
        return $this->coef_active;
    }

    public function setCoefActive(float $coef_active): static
    {
        $this->coef_active = $coef_active;

        return $this;
    }

    public function getCoefThreshold(): ?float
    {
        return $this->coef_threshold;
    }

    public function setCoefThreshold(float $coef_threshold): static
    {
        $this->coef_threshold = $coef_threshold;

        return $this;
    }

    public function getCoefVma(): ?float
    {
        return $this->coef_vma;
    }

    public function setCoefVma(float $coef_vma): static
    {
        $this->coef_vma = $coef_vma;

        return $this;
    }

    public function getDefaultPlanMinWeeks(): ?int
    {
        return $this->default_plan_min_weeks;
    }

    public function setDefaultPlanMinWeeks(int $default_plan_min_weeks): static
    {
        $this->default_plan_min_weeks = $default_plan_min_weeks;

        return $this;
    }

    public function getDefaultPlanMaxWeeks(): ?int
    {
        return $this->default_plan_max_weeks;
    }

    public function setDefaultPlanMaxWeeks(int $default_plan_max_weeks): static
    {
        $this->default_plan_max_weeks = $default_plan_max_weeks;

        return $this;
    }

    public function getMissedSessionTolerance(): ?int
    {
        return $this->missed_session_tolerance;
    }

    public function setMissedSessionTolerance(int $missed_session_tolerance): static
    {
        $this->missed_session_tolerance = $missed_session_tolerance;

        return $this;
    }

    public function getSuccessValidationRate(): ?float
    {
        return $this->success_validation_rate;
    }

    public function setSuccessValidationRate(float $success_validation_rate): static
    {
        $this->success_validation_rate = $success_validation_rate;

        return $this;
    }
}
