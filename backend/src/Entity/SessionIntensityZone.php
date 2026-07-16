<?php

namespace App\Entity;

use App\Repository\SessionIntensityZoneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionIntensityZoneRepository::class)]
class SessionIntensityZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $duration_pcent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDurationPcent(): ?float
    {
        return $this->duration_pcent;
    }

    public function setDurationPcent(float $duration_pcent): static
    {
        $this->duration_pcent = $duration_pcent;

        return $this;
    }
}
