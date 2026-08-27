<?php

namespace App\Entity;

use App\Repository\AlgorithmParameterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AlgorithmParameterRepository::class)]
class AlgorithmParameter
{
    public const KEY_PROGRESSION_MAX_PERCENT = 'progression_max_percent';
    public const KEY_RECOVERY_WEEK_FREQUENCY = 'recovery_week_frequency';
    public const KEY_MAX_SESSIONS_DISCOVERY = 'max_sessions_discovery';
    public const KEY_MAX_SESSIONS_INTERMEDIATE = 'max_sessions_intermediate';
    public const KEY_MAX_SESSIONS_PERFORMANCE = 'max_sessions_performance';
    public const KEY_LONG_RUN_RATIO_MAX = 'long_run_ratio_max';
    public const KEY_COEF_ENDURANCE = 'coef_endurance';
    public const KEY_COEF_ACTIVE = 'coef_active';
    public const KEY_COEF_THRESHOLD = 'coef_threshold';
    public const KEY_COEF_VMA = 'coef_vma';
    public const KEY_DEFAULT_PLAN_MIN_WEEKS = 'default_plan_min_weeks';
    public const KEY_DEFAULT_PLAN_MAX_WEEKS = 'default_plan_max_weeks';
    public const KEY_MISSED_SESSION_TOLERANCE = 'missed_session_tolerance';
    public const KEY_SUCCESS_VALIDATION_RATE = 'success_validation_rate';

    /**
     * Paramètres définis par le MCD. Chaque clé correspond à une ligne afin de
     * permettre une modification et une traçabilité indépendantes.
     *
     * @var list<string>
     */
    public const SUPPORTED_KEYS = [
        self::KEY_PROGRESSION_MAX_PERCENT,
        self::KEY_RECOVERY_WEEK_FREQUENCY,
        self::KEY_MAX_SESSIONS_DISCOVERY,
        self::KEY_MAX_SESSIONS_INTERMEDIATE,
        self::KEY_MAX_SESSIONS_PERFORMANCE,
        self::KEY_LONG_RUN_RATIO_MAX,
        self::KEY_COEF_ENDURANCE,
        self::KEY_COEF_ACTIVE,
        self::KEY_COEF_THRESHOLD,
        self::KEY_COEF_VMA,
        self::KEY_DEFAULT_PLAN_MIN_WEEKS,
        self::KEY_DEFAULT_PLAN_MAX_WEEKS,
        self::KEY_MISSED_SESSION_TOLERANCE,
        self::KEY_SUCCESS_VALIDATION_RATE,
    ];

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
        if ($this->parameterKey !== self::KEY_SUCCESS_VALIDATION_RATE || $this->parameterValue === null) {
            throw new \LogicException('Le paramètre attendu est "success_validation_rate".');
        }

        if ($this->parameterValue < 0 || $this->parameterValue > 100) {
            throw new \LogicException('Le taux de validation doit être compris entre 0 et 100.');
        }

        return $this->parameterValue;
    }

    public function getProgressionMaxPercent(): float
    {
        if ($this->parameterKey !== self::KEY_PROGRESSION_MAX_PERCENT || $this->parameterValue === null) {
            throw new \LogicException('Le paramètre attendu est "progression_max_percent".');
        }

        if ($this->parameterValue < 0 || $this->parameterValue > 30) {
            throw new \LogicException('La progression maximale doit être comprise entre 0 et 30 %.');
        }

        return $this->parameterValue;
    }

    public function getRecoveryWeekFrequency(): int
    {
        if ($this->parameterKey !== self::KEY_RECOVERY_WEEK_FREQUENCY || $this->parameterValue === null) {
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

    #[Assert\Callback]
    public function validateConfiguredValue(ExecutionContextInterface $context): void
    {
        if ($this->parameterKey === null || $this->parameterValue === null) {
            return;
        }

        $error = self::valueValidationError($this->parameterKey, $this->parameterValue);
        if ($error !== null) {
            $context->buildViolation($error)->atPath('parameterValue')->addViolation();
        }
    }

    public static function valueValidationError(string $key, float $value): ?string
    {
        if (!in_array($key, self::SUPPORTED_KEYS, true)) {
            return sprintf('Le paramètre algorithmique "%s" est inconnu.', $key);
        }

        if (!is_finite($value)) {
            return sprintf('La valeur du paramètre "%s" doit être un nombre fini.', $key);
        }

        $bounds = match ($key) {
            self::KEY_PROGRESSION_MAX_PERCENT => [0.0, 30.0, 'La progression maximale doit être comprise entre 0 et 30 %.'],
            self::KEY_SUCCESS_VALIDATION_RATE => [0.0, 100.0, 'Le taux de validation doit être compris entre 0 et 100 %.'],
            self::KEY_RECOVERY_WEEK_FREQUENCY => [1.0, 12.0, 'La fréquence de récupération doit être comprise entre 1 et 12 semaines.'],
            self::KEY_MAX_SESSIONS_DISCOVERY,
            self::KEY_MAX_SESSIONS_INTERMEDIATE,
            self::KEY_MAX_SESSIONS_PERFORMANCE => [1.0, 7.0, 'Le nombre de séances hebdomadaires doit être compris entre 1 et 7.'],
            self::KEY_LONG_RUN_RATIO_MAX => [0.0, 1.0, 'La part maximale de sortie longue doit être comprise entre 0 et 1.'],
            self::KEY_COEF_ENDURANCE,
            self::KEY_COEF_ACTIVE,
            self::KEY_COEF_THRESHOLD,
            self::KEY_COEF_VMA => [0.5, 1.2, 'Le coefficient VMA doit être compris entre 0,5 et 1,2.'],
            self::KEY_DEFAULT_PLAN_MIN_WEEKS,
            self::KEY_DEFAULT_PLAN_MAX_WEEKS => [1.0, 52.0, 'La durée du plan doit être comprise entre 1 et 52 semaines.'],
            self::KEY_MISSED_SESSION_TOLERANCE => [0.0, 10.0, 'La tolérance de séances manquées doit être comprise entre 0 et 10.'],
        };

        if ($value < $bounds[0] || $value > $bounds[1]) {
            return $bounds[2];
        }

        if (in_array($key, [
            self::KEY_RECOVERY_WEEK_FREQUENCY,
            self::KEY_MAX_SESSIONS_DISCOVERY,
            self::KEY_MAX_SESSIONS_INTERMEDIATE,
            self::KEY_MAX_SESSIONS_PERFORMANCE,
            self::KEY_DEFAULT_PLAN_MIN_WEEKS,
            self::KEY_DEFAULT_PLAN_MAX_WEEKS,
            self::KEY_MISSED_SESSION_TOLERANCE,
        ], true) && floor($value) !== $value) {
            return sprintf('La valeur du paramètre "%s" doit être un nombre entier.', $key);
        }

        return null;
    }
}
