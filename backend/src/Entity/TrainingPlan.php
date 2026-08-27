<?php

namespace App\Entity;

use App\Repository\TrainingPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TrainingPlanRepository::class)]
#[ORM\Index(name: 'IDX_TRAINING_PLAN_MONITORING_CREATED_AT', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_TRAINING_PLAN_MONITORING_FEASIBILITY', columns: ['feasibility_indicator'])]
#[ORM\Index(name: 'IDX_TRAINING_PLAN_MONITORING_PROGRESS', columns: ['progress_score'])]
class TrainingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    /**
     * Valeurs prévues :
     * discovery, intermediate, performance
     */
    #[ORM\Column(length: 30)]
    private ?string $poleType = null;

    /**
     * Valeurs prévues :
     * distance, time
     */
    #[ORM\Column(length: 20)]
    private ?string $targetType = null;

    #[ORM\Column]
    private ?float $targetValue = null;

    /**
     * Valeurs prévues :
     * km, min
     */
    #[ORM\Column(length: 10)]
    private ?string $targetUnit = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetDurationMinutes = null;

    /**
     * Valeurs prévues :
     * route, trail
     */
    #[ORM\Column(length: 20)]
    private ?string $terrainType = null;

    /**
     * Dénivelé cible facultatif pour un objectif sur route.
     */
    #[ORM\Column(nullable: true)]
    private ?int $elevationTargetDPlus = null;

    /**
     * Valeurs possibles :
     * FAIBLE, MOYEN, BON, OPTIMAL
     */
    #[ORM\Column(length: 30)]
    private ?string $feasibilityIndicator = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure ou égale à la date de début.',
    )]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La durée du plan doit être strictement positive.')]
    private ?int $durationWeeks = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 1])]
    private int $currentWeek = 1;

    #[ORM\Column]
    private float $progressScore = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'trainingPlans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(
        targetEntity: Comment::class,
        mappedBy: 'trainingPlan'
    )]
    private Collection $comments;

    /**
     * @var Collection<int, Session>
     */
    #[ORM\OneToMany(
        targetEntity: Session::class,
        mappedBy: 'trainingPlan',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy([
        'weekIndex' => 'ASC',
        'dayOfWeek' => 'ASC',
    ])]
    private Collection $sessions;

    /**
     * @var list<array{
     *     adaptedAt: string,
     *     successRate: float,
     *     successValidationRate: float,
     *     decision: string,
     *     loadFactor: float,
     *     reason: string,
     *     modification: string
     * }>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $adaptationHistory = [];

    /**
     * @var list<array{
     *     typeAnomaly: string,
     *     description: string,
     *     createdAt: string,
     *     resolved: bool,
     *     resolvedAt: string|null
     * }>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $monitoringHistory = [];

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateActivePlanDates(ExecutionContextInterface $context): void
    {
        if ($this->isActive && $this->endDate === null) {
            $context->buildViolation('La date de fin est obligatoire pour un plan actif.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

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
        $this->name = trim($name);

        return $this;
    }

    public function getPoleType(): ?string
    {
        return $this->poleType;
    }

    public function setPoleType(string $poleType): static
    {
        $this->poleType = $poleType;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetValue(): ?float
    {
        return $this->targetValue;
    }

    public function setTargetValue(float $targetValue): static
    {
        $this->targetValue = $targetValue;

        return $this;
    }

    public function getTargetUnit(): ?string
    {
        return $this->targetUnit;
    }

    public function setTargetUnit(string $targetUnit): static
    {
        $this->targetUnit = $targetUnit;

        return $this;
    }

    public function getTargetDurationMinutes(): ?int
    {
        return $this->targetDurationMinutes;
    }

    public function setTargetDurationMinutes(?int $targetDurationMinutes): static
    {
        $this->targetDurationMinutes = $targetDurationMinutes;

        return $this;
    }

    public function getTerrainType(): ?string
    {
        return $this->terrainType;
    }

    public function setTerrainType(string $terrainType): static
    {
        $this->terrainType = $terrainType;

        return $this;
    }

    public function getElevationTargetDPlus(): ?int
    {
        return $this->elevationTargetDPlus;
    }

    public function setElevationTargetDPlus(
        ?int $elevationTargetDPlus
    ): static {
        $this->elevationTargetDPlus = $elevationTargetDPlus;

        return $this;
    }

    public function getFeasibilityIndicator(): ?string
    {
        return $this->feasibilityIndicator;
    }

    public function setFeasibilityIndicator(
        string $feasibilityIndicator
    ): static {
        $this->feasibilityIndicator = $feasibilityIndicator;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(
        \DateTimeImmutable $startDate
    ): static {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(
        \DateTimeImmutable $endDate
    ): static {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDurationWeeks(): ?int
    {
        return $this->durationWeeks;
    }

    public function setDurationWeeks(int $durationWeeks): static
    {
        $this->durationWeeks = $durationWeeks;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCurrentWeek(): int
    {
        return $this->currentWeek;
    }

    public function setCurrentWeek(int $currentWeek): static
    {
        $this->currentWeek = $currentWeek;

        return $this;
    }

    public function getProgressPercentage(): int
    {
        if ($this->durationWeeks === null || $this->durationWeeks <= 0) {
            return 0;
        }

        $percentage = ($this->currentWeek / $this->durationWeeks) * 100;

        return (int) round(min(100, max(0, $percentage)));
    }

    public function getProgressScore(): float
    {
        return $this->progressScore;
    }

    public function setProgressScore(float $progressScore): static
    {
        $this->progressScore = $progressScore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTrainingPlan($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if (
            $this->comments->removeElement($comment)
            && $comment->getTrainingPlan() === $this
        ) {
            $comment->setTrainingPlan(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(Session $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setTrainingPlan($this);
        }

        return $this;
    }

    public function removeSession(Session $session): static
    {
        if (
            $this->sessions->removeElement($session)
            && $session->getTrainingPlan() === $this
        ) {
            $session->setTrainingPlan(null);
        }

        return $this;
    }

    /**
     * @return list<array{
     *     adaptedAt: string,
     *     successRate: float,
     *     successValidationRate: float,
     *     decision: string,
     *     loadFactor: float,
     *     reason: string,
     *     modification: string
     * }>
     */
    public function getAdaptationHistory(): array
    {
        return $this->adaptationHistory;
    }

    /**
     * @return list<array{
     *     typeAnomaly: string,
     *     description: string,
     *     createdAt: string,
     *     resolved: bool,
     *     resolvedAt: string|null
     * }>
     */
    public function getMonitoringHistory(): array
    {
        return $this->monitoringHistory;
    }

    /**
     * @param array<string, string> $currentAnomalies Types indexés par leur description
     */
    public function synchronizeMonitoringHistory(
        array $currentAnomalies,
        ?\DateTimeImmutable $analyzedAt = null,
    ): bool {
        $analyzedAt ??= new \DateTimeImmutable();
        $changed = false;
        $activeTypes = [];

        foreach ($this->monitoringHistory as &$entry) {
            if ($entry['resolved']) {
                continue;
            }

            if (!array_key_exists($entry['typeAnomaly'], $currentAnomalies)) {
                $entry['resolved'] = true;
                $entry['resolvedAt'] = $analyzedAt->format(\DateTimeInterface::ATOM);
                $changed = true;
                continue;
            }

            $activeTypes[$entry['typeAnomaly']] = true;
        }
        unset($entry);

        foreach ($currentAnomalies as $type => $description) {
            if (isset($activeTypes[$type])) {
                continue;
            }

            $this->monitoringHistory[] = [
                'typeAnomaly' => $type,
                'description' => $description,
                'createdAt' => $analyzedAt->format(\DateTimeInterface::ATOM),
                'resolved' => false,
                'resolvedAt' => null,
            ];
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array{
     *     adaptedAt: string,
     *     successRate: float,
     *     successValidationRate: float,
     *     decision: string,
     *     loadFactor: float,
     *     reason: string,
     *     modification: string
     * } $history
     */
    public function addAdaptationHistory(array $history): static
    {
        array_unshift($this->adaptationHistory, $history);

        return $this;
    }
}
