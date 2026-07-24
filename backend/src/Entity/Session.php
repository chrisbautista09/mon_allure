<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Numéro de la semaine dans le plan.
     */
    #[ORM\Column]
    private ?int $weekIndex = null;

    /**
     * Jour de la semaine :
     * 1 = lundi, 7 = dimanche.
     */
    #[ORM\Column]
    private ?int $dayOfWeek = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Exemples :
     * endurance, active, threshold, vma,
     * long_run, recovery.
     */
    #[ORM\Column(length: 30)]
    private ?string $sessionType = null;

    #[ORM\Column(nullable: true)]
    private ?float $plannedDistanceKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $plannedDurationMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $plannedElevationDPlus = null;

    /**
     * Coefficient appliqué à la VMA.
     * Exemple : 0.70 pour l'endurance fondamentale.
     */
    #[ORM\Column(nullable: true)]
    private ?float $plannedVmaCoef = null;

    /**
     * Zone cardiaque prévue :
     * Z1, Z2, Z3, Z4 ou Z5.
     */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $plannedFcmZone = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    /**
     * Valeurs prévues :
     * planned, done, missed.
     */
    #[ORM\Column(length: 20, options: ['default' => 'planned'])]
    private string $status = 'planned';

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrainingPlan $trainingPlan = null;

    /**
     * @var Collection<int, Performance>
     */
    #[ORM\OneToMany(
        targetEntity: Performance::class,
        mappedBy: 'session'
    )]
    private Collection $performances;

    /**
     * @var Collection<int, SessionIntensityZone>
     */
    #[ORM\OneToMany(
        targetEntity: SessionIntensityZone::class,
        mappedBy: 'session',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $sessionIntensityZones;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(
        targetEntity: Comment::class,
        mappedBy: 'session'
    )]
    private Collection $comments;

    public function __construct()
    {
        $this->performances = new ArrayCollection();
        $this->sessionIntensityZones = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekIndex(): ?int
    {
        return $this->weekIndex;
    }

    public function setWeekIndex(int $weekIndex): static
    {
        $this->weekIndex = $weekIndex;

        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSessionType(): ?string
    {
        return $this->sessionType;
    }

    public function setSessionType(string $sessionType): static
    {
        $this->sessionType = $sessionType;

        return $this;
    }

    public function getPlannedDistanceKm(): ?float
    {
        return $this->plannedDistanceKm;
    }

    public function setPlannedDistanceKm(
        ?float $plannedDistanceKm
    ): static {
        $this->plannedDistanceKm = $plannedDistanceKm;

        return $this;
    }

    public function getPlannedDurationMin(): ?int
    {
        return $this->plannedDurationMin;
    }

    public function setPlannedDurationMin(
        ?int $plannedDurationMin
    ): static {
        $this->plannedDurationMin = $plannedDurationMin;

        return $this;
    }

    public function getPlannedElevationDPlus(): ?int
    {
        return $this->plannedElevationDPlus;
    }

    public function setPlannedElevationDPlus(
        ?int $plannedElevationDPlus
    ): static {
        $this->plannedElevationDPlus = $plannedElevationDPlus;

        return $this;
    }

    public function getPlannedVmaCoef(): ?float
    {
        return $this->plannedVmaCoef;
    }

    public function setPlannedVmaCoef(
        ?float $plannedVmaCoef
    ): static {
        $this->plannedVmaCoef = $plannedVmaCoef;

        return $this;
    }

    public function getPlannedFcmZone(): ?string
    {
        return $this->plannedFcmZone;
    }

    public function setPlannedFcmZone(
        ?string $plannedFcmZone
    ): static {
        $this->plannedFcmZone = $plannedFcmZone;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->trainingPlan;
    }

    public function setTrainingPlan(
        ?TrainingPlan $trainingPlan
    ): static {
        $this->trainingPlan = $trainingPlan;

        return $this;
    }

    /**
     * @return Collection<int, Performance>
     */
    public function getPerformances(): Collection
    {
        return $this->performances;
    }

    public function addPerformance(
        Performance $performance
    ): static {
        if (!$this->performances->contains($performance)) {
            $this->performances->add($performance);
            $performance->setSession($this);
        }

        return $this;
    }

    public function removePerformance(
        Performance $performance
    ): static {
        if (
            $this->performances->removeElement($performance)
            && $performance->getSession() === $this
        ) {
            $performance->setSession(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, SessionIntensityZone>
     */
    public function getSessionIntensityZones(): Collection
    {
        return $this->sessionIntensityZones;
    }

    public function addSessionIntensityZone(
        SessionIntensityZone $sessionIntensityZone
    ): static {
        if (
            !$this->sessionIntensityZones
                ->contains($sessionIntensityZone)
        ) {
            $this->sessionIntensityZones
                ->add($sessionIntensityZone);

            $sessionIntensityZone->setSession($this);
        }

        return $this;
    }

    public function removeSessionIntensityZone(
        SessionIntensityZone $sessionIntensityZone
    ): static {
        if (
            $this->sessionIntensityZones
                ->removeElement($sessionIntensityZone)
            && $sessionIntensityZone->getSession() === $this
        ) {
            $sessionIntensityZone->setSession(null);
        }

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
            $comment->setSession($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if (
            $this->comments->removeElement($comment)
            && $comment->getSession() === $this
        ) {
            $comment->setSession(null);
        }

        return $this;
    }
}