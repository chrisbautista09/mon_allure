<?php

namespace App\Entity;

use App\Repository\IntensityZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, SessionIntensityZone>
     */
    #[ORM\OneToMany(targetEntity: SessionIntensityZone::class, mappedBy: 'intensityZone')]
    private Collection $sessionIntensityZones;

    public function __construct()
    {
        $this->sessionIntensityZones = new ArrayCollection();
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

    /**
     * @return Collection<int, SessionIntensityZone>
     */
    public function getSessionIntensityZones(): Collection
    {
        return $this->sessionIntensityZones;
    }

    public function addSessionIntensityZone(SessionIntensityZone $sessionIntensityZone): static
    {
        if (!$this->sessionIntensityZones->contains($sessionIntensityZone)) {
            $this->sessionIntensityZones->add($sessionIntensityZone);
            $sessionIntensityZone->setIntensityZone($this);
        }

        return $this;
    }

    public function removeSessionIntensityZone(SessionIntensityZone $sessionIntensityZone): static
    {
        if ($this->sessionIntensityZones->removeElement($sessionIntensityZone)) {
            // set the owning side to null (unless already changed)
            if ($sessionIntensityZone->getIntensityZone() === $this) {
                $sessionIntensityZone->setIntensityZone(null);
            }
        }

        return $this;
    }
}
