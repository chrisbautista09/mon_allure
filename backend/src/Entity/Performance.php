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

    #[ORM\Column]
    private ?int $time_realized = null;

    #[ORM\Column]
    private ?float $distance_realized = null;

    #[ORM\Column(nullable: true)]
    private ?int $elevation_d_plus = null;

    #[ORM\Column(length: 255)]
    private ?string $terrain_type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $create_at = null;

    #[ORM\OneToOne(inversedBy: 'performance', cascade: ['persist', 'remove'])]
    private ?Session $session = null;

    #[ORM\ManyToOne(inversedBy: 'performances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimeRealized(): ?int
    {
        return $this->time_realized;
    }

    public function setTimeRealized(int $time_realized): static
    {
        $this->time_realized = $time_realized;

        return $this;
    }

    public function getDistanceRealized(): ?float
    {
        return $this->distance_realized;
    }

    public function setDistanceRealized(float $distance_realized): static
    {
        $this->distance_realized = $distance_realized;

        return $this;
    }

    public function getElevationDPlus(): ?int
    {
        return $this->elevation_d_plus;
    }

    public function setElevationDPlus(?int $elevation_d_plus): static
    {
        $this->elevation_d_plus = $elevation_d_plus;

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

    public function getCreateAt(): ?\DateTime
    {
        return $this->create_at;
    }

    public function setCreateAt(\DateTime $create_at): static
    {
        $this->create_at = $create_at;

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
