<?php

namespace App\Entity;

use App\Repository\IntensityZoneRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntensityZoneRepository::class)]
class IntensityZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $heart_rate_min_pcent = null;

    #[ORM\Column]
    private ?float $heart_rate_max_pcent = null;

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
        $this->name = $name;

        return $this;
    }

    public function getHeartRateMinPcent(): ?float
    {
        return $this->heart_rate_min_pcent;
    }

    public function setHeartRateMinPcent(float $heart_rate_min_pcent): static
    {
        $this->heart_rate_min_pcent = $heart_rate_min_pcent;

        return $this;
    }

    public function getHeartRateMaxPcent(): ?float
    {
        return $this->heart_rate_max_pcent;
    }

    public function setHeartRateMaxPcent(float $heart_rate_max_pcent): static
    {
        $this->heart_rate_max_pcent = $heart_rate_max_pcent;

        return $this;
    }
}
