<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_IDENTIFIER_EMAIL',
    fields: ['email']
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe haché de l'utilisateur.
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $pseudo = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private ?Profile $profile = null;

    /**
     * @var Collection<int, TrainingPlan>
     */
    #[ORM\OneToMany(
        targetEntity: TrainingPlan::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $trainingPlans;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(
        targetEntity: Comment::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $comments;

    /**
     * @var Collection<int, Performance>
     */
    #[ORM\OneToMany(
        targetEntity: Performance::class,
        mappedBy: 'user',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $performances;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->trainingPlans = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->performances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = trim($pseudo);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * Empêche le stockage du véritable hash du mot de passe
     * dans les données sérialisées de la session.
     */
    public function __serialize(): array
    {
        $data = (array) $this;

        $data["\0" . self::class . "\0password"] = hash(
            'crc32c',
            (string) $this->password
        );

        return $data;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
{
    if ($profile !== null && $profile->getUser() !== $this) {
        $profile->setUser($this);
    }

    $this->profile = $profile;

    return $this;
}

    /**
     * @return Collection<int, TrainingPlan>
     */
    public function getTrainingPlans(): Collection
    {
        return $this->trainingPlans;
    }

    public function addTrainingPlan(TrainingPlan $trainingPlan): static
    {
        if (!$this->trainingPlans->contains($trainingPlan)) {
            $this->trainingPlans->add($trainingPlan);
            $trainingPlan->setUser($this);
        }

        return $this;
    }

    public function removeTrainingPlan(TrainingPlan $trainingPlan): static
    {
        if (
            $this->trainingPlans->removeElement($trainingPlan)
            && $trainingPlan->getUser() === $this
        ) {
            $trainingPlan->setUser(null);
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
            $comment->setUser($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if (
            $this->comments->removeElement($comment)
            && $comment->getUser() === $this
        ) {
            $comment->setUser(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Performance>
     */
    public function getPerformances(): Collection
    {
        return $this->performances;
    }

    public function addPerformance(Performance $performance): static
    {
        if (!$this->performances->contains($performance)) {
            $this->performances->add($performance);
            $performance->setUser($this);
        }

        return $this;
    }

    public function removePerformance(Performance $performance): static
    {
        if (
            $this->performances->removeElement($performance)
            && $performance->getUser() === $this
        ) {
            $performance->setUser(null);
        }

        return $this;
    }
}