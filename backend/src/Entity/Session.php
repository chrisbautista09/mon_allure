<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $instructions = null;

    #[ORM\Column]
    private ?int $target_duration = null;

    #[ORM\Column]
    private ?float $target_distance = null;

    #[ORM\Column]
    private ?float $target_speed = null;

    #[ORM\Column(length: 255)]
    private ?string $target_pace = null;

    #[ORM\Column]
    private ?int $target_hr_min = null;

    #[ORM\Column]
    private ?int $target_hr_max = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\OneToOne(mappedBy: 'session', cascade: ['persist', 'remove'])]
    private ?Performance $performance = null;

    /**
     * @var Collection<int, SessionIntensityZone>
     */
    #[ORM\OneToMany(targetEntity: SessionIntensityZone::class, mappedBy: 'session')]
    private Collection $sessionIntensityZones;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'session')]
    private Collection $comments;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TrainingPlan $training_plan = null;

    public function __construct()
    {
        $this->sessionIntensityZones = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function getTargetDuration(): ?int
    {
        return $this->target_duration;
    }

    public function setTargetDuration(int $target_duration): static
    {
        $this->target_duration = $target_duration;

        return $this;
    }

    public function getTargetDistance(): ?float
    {
        return $this->target_distance;
    }

    public function setTargetDistance(float $target_distance): static
    {
        $this->target_distance = $target_distance;

        return $this;
    }

    public function getTargetSpeed(): ?float
    {
        return $this->target_speed;
    }

    public function setTargetSpeed(float $target_speed): static
    {
        $this->target_speed = $target_speed;

        return $this;
    }

    public function getTargetPace(): ?string
    {
        return $this->target_pace;
    }

    public function setTargetPace(string $target_pace): static
    {
        $this->target_pace = $target_pace;

        return $this;
    }

    public function getTargetHrMin(): ?int
    {
        return $this->target_hr_min;
    }

    public function setTargetHrMin(int $target_hr_min): static
    {
        $this->target_hr_min = $target_hr_min;

        return $this;
    }

    public function getTargetHrMax(): ?int
    {
        return $this->target_hr_max;
    }

    public function setTargetHrMax(int $target_hr_max): static
    {
        $this->target_hr_max = $target_hr_max;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPerformance(): ?Performance
    {
        return $this->performance;
    }

    public function setPerformance(?Performance $performance): static
    {
        // unset the owning side of the relation if necessary
        if ($performance === null && $this->performance !== null) {
            $this->performance->setSession(null);
        }

        // set the owning side of the relation if necessary
        if ($performance !== null && $performance->getSession() !== $this) {
            $performance->setSession($this);
        }

        $this->performance = $performance;

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
            $sessionIntensityZone->setSession($this);
        }

        return $this;
    }

    public function removeSessionIntensityZone(SessionIntensityZone $sessionIntensityZone): static
    {
        if ($this->sessionIntensityZones->removeElement($sessionIntensityZone)) {
            // set the owning side to null (unless already changed)
            if ($sessionIntensityZone->getSession() === $this) {
                $sessionIntensityZone->setSession(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setSession($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getSession() === $this) {
                $comment->setSession(null);
            }
        }

        return $this;
    }

    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->training_plan;
    }

    public function setTrainingPlan(?TrainingPlan $training_plan): static
    {
        $this->training_plan = $training_plan;

        return $this;
    }
}
