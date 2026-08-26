<?php

namespace App\Entity;

use App\Repository\PerformanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PerformanceRepository::class)]
class Performance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Distance réellement parcourue, en kilomètres.
     */
    #[ORM\Column]
    #[Assert\NotNull(message: 'La distance réalisée est obligatoire.')]
    #[Assert\Positive(message: 'La distance réalisée doit être supérieure à zéro.')]
    private ?float $distanceKm = null;

    /**
     * Durée réellement effectuée, en secondes.
     */
    #[ORM\Column]
    #[Assert\NotNull(message: 'Le temps réalisé est obligatoire.')]
    #[Assert\Positive(message: 'Le temps réalisé doit être supérieur à zéro.')]
    private ?int $durationSec = null;

    /**
     * Dénivelé positif réellement parcouru.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le dénivelé ne peut pas être négatif.')]
    private ?int $elevationDPlus = null;

    /**
     * Fréquence cardiaque moyenne.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 30,
        max: 230,
        notInRangeMessage: 'La fréquence cardiaque moyenne doit être comprise entre {{ min }} et {{ max }} bpm.',
    )]
    private ?int $avgHr = null;

    /**
     * Commentaire libre de l'utilisateur.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $comment = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date d’enregistrement est obligatoire.')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(inversedBy: 'performance')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La performance doit être rattachée à une séance.')]
    private ?Session $session = null;

    #[ORM\ManyToOne(inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La performance doit appartenir à un utilisateur.')]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDistanceKm(): ?float
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(float $distanceKm): static
    {
        $this->distanceKm = $distanceKm;

        return $this;
    }

    public function getDurationSec(): ?int
    {
        return $this->durationSec;
    }

    public function setDurationSec(int $durationSec): static
    {
        $this->durationSec = $durationSec;

        return $this;
    }

    public function getElevationDPlus(): ?int
    {
        return $this->elevationDPlus;
    }

    public function setElevationDPlus(?int $elevationDPlus): static
    {
        $this->elevationDPlus = $elevationDPlus;

        return $this;
    }

    public function getAvgHr(): ?int
    {
        return $this->avgHr;
    }

    public function setAvgHr(?int $avgHr): static
    {
        $this->avgHr = $avgHr;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        if ($this->session === $session) {
            return $this;
        }

        $previousSession = $this->session;
        $this->session = $session;

        if ($previousSession?->getPerformance() === $this) {
            $previousSession->clearPerformance();
        }

        if ($session !== null && $session->getPerformance() !== $this) {
            $session->setPerformance($this);
        }

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
     * Le terrain est celui du plan associé à la séance. La saisie d’une
     * performance impose déjà cette concordance, ce qui évite une donnée
     * dupliquée susceptible de diverger.
     */
    public function getTerrainType(): ?string
    {
        return $this->session?->getTrainingPlan()?->getTerrainType();
    }

    #[Assert\Callback]
    public function validateOwnershipConsistency(ExecutionContextInterface $context): void
    {
        $planOwner = $this->session?->getTrainingPlan()?->getUser();

        if ($this->user !== null && $planOwner !== null && $this->user !== $planOwner) {
            $context->buildViolation('La performance doit appartenir au propriétaire du plan d’entraînement.')
                ->atPath('user')
                ->addViolation();
        }
    }
}
