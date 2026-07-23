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

    /**
     * Exemples : Z1, Z2, Z3, Z4, Z5.
     */
    #[ORM\Column(length: 10, unique: true)]
    private ?string $name = null;

    /**
     * Borne minimale exprimée sous forme de coefficient.
     * Exemple : 0.60 pour 60 % de la VMA.
     */
    #[ORM\Column]
    private ?float $vmaCoefMin = null;

    /**
     * Borne maximale exprimée sous forme de coefficient.
     * Exemple : 0.70 pour 70 % de la VMA.
     */
    #[ORM\Column]
    private ?float $vmaCoefMax = null;

    /**
     * Pourcentage minimal de la FCM.
     * Exemple : 60.0 pour 60 %.
     */
    #[ORM\Column]
    private ?float $fcmPercentMin = null;

    /**
     * Pourcentage maximal de la FCM.
     * Exemple : 70.0 pour 70 %.
     */
    #[ORM\Column]
    private ?float $fcmPercentMax = null;

    /**
     * @var Collection<int, SessionIntensityZone>
     */
    #[ORM\OneToMany(
        targetEntity: SessionIntensityZone::class,
        mappedBy: 'intensityZone'
    )]
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
        $this->name = strtoupper(trim($name));

        return $this;
    }

    public function getVmaCoefMin(): ?float
    {
        return $this->vmaCoefMin;
    }

    public function setVmaCoefMin(float $vmaCoefMin): static
    {
        $this->vmaCoefMin = $vmaCoefMin;

        return $this;
    }

    public function getVmaCoefMax(): ?float
    {
        return $this->vmaCoefMax;
    }

    public function setVmaCoefMax(float $vmaCoefMax): static
    {
        $this->vmaCoefMax = $vmaCoefMax;

        return $this;
    }

    public function getFcmPercentMin(): ?float
    {
        return $this->fcmPercentMin;
    }

    public function setFcmPercentMin(float $fcmPercentMin): static
    {
        $this->fcmPercentMin = $fcmPercentMin;

        return $this;
    }

    public function getFcmPercentMax(): ?float
    {
        return $this->fcmPercentMax;
    }

    public function setFcmPercentMax(float $fcmPercentMax): static
    {
        $this->fcmPercentMax = $fcmPercentMax;

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
        if (!$this->sessionIntensityZones->contains($sessionIntensityZone)) {
            $this->sessionIntensityZones->add($sessionIntensityZone);
            $sessionIntensityZone->setIntensityZone($this);
        }

        return $this;
    }

    public function removeSessionIntensityZone(
        SessionIntensityZone $sessionIntensityZone
    ): static {
        if (
            $this->sessionIntensityZones->removeElement($sessionIntensityZone)
            && $sessionIntensityZone->getIntensityZone() === $this
        ) {
            $sessionIntensityZone->setIntensityZone(null);
        }

        return $this;
    }
}