<?php

namespace App\Entity;

use App\Repository\SessionIntensityZoneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionIntensityZoneRepository::class)]
#[ORM\UniqueConstraint(
    name: 'unique_session_intensity_zone',
    columns: ['session_id', 'intensity_zone_id']
)]
class SessionIntensityZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Pourcentage de la séance réalisé dans cette zone.
     * Exemple : 60.0 pour 60 %.
     */
    #[ORM\Column]
    private ?float $durationPercent = null;

    #[ORM\ManyToOne(inversedBy: 'sessionIntensityZones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?IntensityZone $intensityZone = null;

    #[ORM\ManyToOne(inversedBy: 'sessionIntensityZones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDurationPercent(): ?float
    {
        return $this->durationPercent;
    }

    public function setDurationPercent(float $durationPercent): static
    {
        $this->durationPercent = $durationPercent;

        return $this;
    }

    public function getIntensityZone(): ?IntensityZone
    {
        return $this->intensityZone;
    }

    public function setIntensityZone(?IntensityZone $intensityZone): static
    {
        $this->intensityZone = $intensityZone;

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
}