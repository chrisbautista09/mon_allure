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

    #[ORM\ManyToOne(inversedBy: 'sessionIntensityZones')]
    #[ORM\JoinColumn(nullable: false)]
    private ?IntensityZone $intensityZone = null;

    #[ORM\ManyToOne(inversedBy: 'sessionIntensityZones')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;


    public function getId(): ?int
    {
        return $this->id;
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
