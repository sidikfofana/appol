<?php

namespace App\Entity;

use App\Repository\PharmacyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PharmacyRepository::class)]
class Pharmacy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pharmacies', 'users'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pharmacies', 'users'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacies'])]
    private ?string $address = null;

    #[ORM\ManyToOne(inversedBy: 'pharmacies')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pharmacies'])]
    private ?Country $country = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pharmacies'])]
    private ?User $owner = null;

    #[ORM\Column(type: Types::ARRAY)]
    #[Groups(['pharmacies'])]
    private array $opening_day = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['pharmacies'])]
    private ?bool $is_online = null;

    #[ORM\ManyToOne(inversedBy: 'pharmacies')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['pharmacies'])]
    private ?City $city = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'pharmacy')]
    private Collection $users;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'pharmacy')]
    private Collection $pharmacy;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'pharmacies')]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacies'])]
    private ?string $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->pharmacy = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getOpeningDay(): array
    {
        return $this->opening_day;
    }

    public function setOpeningDay(array $opening_day): static
    {
        $this->opening_day = $opening_day;

        return $this;
    }

    public function isOnline(): ?bool
    {
        return $this->is_online;
    }

    public function setIsOnline(?bool $is_online): static
    {
        $this->is_online = $is_online;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): static
    {
        $this->city = $city;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setPharmacy($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getPharmacy() === $this) {
                $user->setPharmacy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getPharmacy(): Collection
    {
        return $this->pharmacy;
    }

    public function addPharmacy(Order $pharmacy): static
    {
        if (!$this->pharmacy->contains($pharmacy)) {
            $this->pharmacy->add($pharmacy);
            $pharmacy->setPharmacy($this);
        }

        return $this;
    }

    public function removePharmacy(Order $pharmacy): static
    {
        if ($this->pharmacy->removeElement($pharmacy)) {
            // set the owning side to null (unless already changed)
            if ($pharmacy->getPharmacy() === $this) {
                $pharmacy->setPharmacy(null);
            }
        }

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }
}
