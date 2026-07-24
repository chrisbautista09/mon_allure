<?php

namespace App\Entity;

use App\Repository\TrainingPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingPlanRepository::class)]
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
     * realistic, ambitious, dangerous
     */
    #[ORM\Column(length: 30)]
    private ?string $feasibilityIndicator = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private ?int $durationWeeks = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 1])]
    private int $currentWeek = 1;

    #[ORM\Column]
    private float $progressScore = 0.0;

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

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->sessions = new ArrayCollection();
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

    public function getProgressScore(): float
    {
        return $this->progressScore;
    }

    public function setProgressScore(float $progressScore): static
    {
        $this->progressScore = $progressScore;

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
}