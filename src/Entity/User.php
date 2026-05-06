<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "User",
    description: "Représentation d'un utilisateur",
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['users', 'pharmacies'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['users', 'pharmacies'])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    #[Groups(['users', 'pharmacies'])]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    #[Groups(['users'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Groups(['users', 'pharmacies'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['users', 'countries'])]
    private ?Country $country = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['users'])]
    private ?Role $role = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[Groups(['users'])]
    private ?Pharmacy $pharmacy = null;

    #[ORM\Column]
    #[Groups(['users'])]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    #[Groups(['users'])]
    private ?bool $status = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $User;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'user')]
    #[Groups(['orders'])]
    private Collection $orderItems;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Company $company = null;

    public function __construct()
    {
        $this->User = new ArrayCollection();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

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

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPharmacy(): ?Pharmacy
    {
        return $this->pharmacy;
    }

    public function setPharmacy(?Pharmacy $pharmacy): static
    {
        $this->pharmacy = $pharmacy;

        return $this;
    }

    /**
     * Identifiant unique (ancien getUsername)
     */
    public function getUserIdentifier(): string
    {
        // Si l'utilisateur a un email, on l'utilise comme identifiant principal
        // Sinon, on utilise son téléphone
        return $this->email ?: $this->phone ?: '';
    }

    /**
     * Retourne les rôles Symfony.
     * Ici on utilise la relation Role → name
     */
    public function getRoles(): array
    {
        return [$this->role?->getName() ?? 'User'];
    }

    /**
     * Symfony demande cette méthode pour nettoyer les données sensibles
     */
    public function eraseCredentials(): void
    {
        // ex: $this->plainPassword = null;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getUser(): Collection
    {
        return $this->User;
    }

    public function addUser(Order $user): static
    {
        if (!$this->User->contains($user)) {
            $this->User->add($user);
            $user->setUser($this);
        }

        return $this;
    }

    public function removeUser(Order $user): static
    {
        if ($this->User->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getUser() === $this) {
                $user->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setUser($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getUser() === $this) {
                $orderItem->setUser(null);
            }
        }

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }
}
