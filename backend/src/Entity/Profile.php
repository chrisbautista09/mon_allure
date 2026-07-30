<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column]
    private ?int $age = null;

    #[ORM\Column(nullable: true)]
    private ?float $vma = null;

    #[ORM\Column(nullable: true)]
    private ?float $vo2max = null;

    #[ORM\Column(nullable: true)]
    private ?int $fcm = null;

    #[ORM\Column(nullable: true)]
    private ?int $fcr = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\OneToOne(inversedBy: 'profile', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }
   
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $last_name): static
    {
        $this->lastName = $last_name;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getVma(): ?float
    {
        return $this->vma;
    }

    public function setVma(?float $vma): static
    {
        $this->vma = $vma;

        return $this;
    }

    public function getVo2max(): ?float
    {
        return $this->vo2max;
    }

    public function setVo2max(?float $vo2max): static
    {
        $this->vo2max = $vo2max;

        return $this;
    }

    public function getFcm(): ?int
    {
        return $this->fcm;
    }

    public function setFcm(?int $fcm): static
    {
        $this->fcm = $fcm;

        return $this;
    }

    public function getFcr(): ?int
    {
        return $this->fcr;
    }

    public function setFcr(?int $fcr): static
    {
        $this->fcr = $fcr;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
