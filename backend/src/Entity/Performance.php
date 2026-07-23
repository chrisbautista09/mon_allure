<?php

namespace App\Entity;

use App\Repository\PerformanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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
    private ?float $distanceKm = null;

    /**
     * Durée réellement effectuée, en secondes.
     */
    #[ORM\Column]
    private ?int $durationSec = null;

    /**
     * Dénivelé positif réellement parcouru.
     */
    #[ORM\Column(nullable: true)]
    private ?int $elevationDPlus = null;

    /**
     * Fréquence cardiaque moyenne.
     */
    #[ORM\Column(nullable: true)]
    private ?int $avgHr = null;

    /**
     * Commentaire libre de l'utilisateur.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Session $session = null;

    #[ORM\ManyToOne(inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
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
        $this->session = $session;

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