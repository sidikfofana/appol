<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\WithdrawalType;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['orders'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['orders'])]
    private ?string $uidn = null;

    #[ORM\ManyToOne(inversedBy: 'User')]
    #[Groups(['orders'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'pharmacy')]
    #[Groups(['orders'])]
    private ?Pharmacy $pharmacy = null;

    #[ORM\Column(length: 255)]
    #[Groups(['orders'])]
    private ?string $identity_document = null;

    #[ORM\Column]
    #[Groups(['orders'])]
    private array $prescription_files = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['orders'])]
    private ?string $comment = null;

    #[ORM\Column(enumType: WithdrawalType::class)]
    #[Groups(['orders'])]
    private ?WithdrawalType $withdrawal_type = null;

    #[ORM\Column(enumType: OrderStatus::class)]
    #[Groups(['orders'])]
    private ?OrderStatus $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['orders'])]
    private ?string $qr_code = null;

    #[ORM\Column]
    #[Groups(['orders'])]
    private ?\DateTime $created_date = null;

    #[ORM\Column]
    #[Groups(['orders'])]
    private ?\DateTime $updated_date = null;

    #[ORM\Column(nullable: true)]
    private ?float $total_amount = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'commande')]
    private Collection $orderItems;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'order')]
    private Collection $payment;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->payment = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUidn(): ?string
    {
        return $this->uidn;
    }

    public function setUidn(string $uidn): static
    {
        $this->uidn = $uidn;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getIdentityDocument(): ?string
    {
        return $this->identity_document;
    }

    public function setIdentityDocument(string $identity_document): static
    {
        $this->identity_document = $identity_document;

        return $this;
    }

    public function getPrescriptionFiles(): array
    {
        return $this->prescription_files;
    }

    public function setPrescriptionFiles(array $prescription_files): static
    {
        $this->prescription_files = $prescription_files;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getWithdrawalType(): ?WithdrawalType
    {
        return $this->withdrawal_type;
    }

    public function setWithdrawalType(WithdrawalType $withdrawal_type): static
    {
        $this->withdrawal_type = $withdrawal_type;

        return $this;
    }

    public function getStatus(): ?OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getQrCode(): ?string
    {
        return $this->qr_code;
    }

    public function setQrCode(?string $qr_code): static
    {
        $this->qr_code = $qr_code;

        return $this;
    }

    public function getCreatedDate(): ?\DateTime
    {
        return $this->created_date;
    }

    public function setCreatedDate(\DateTime $created_date): static
    {
        $this->created_date = $created_date;

        return $this;
    }

    public function getUpdatedDate(): ?\DateTime
    {
        return $this->updated_date;
    }

    public function setUpdatedDate(\DateTime $updated_date): static
    {
        $this->updated_date = $updated_date;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->total_amount;
    }

    public function setTotalAmount(?float $total_amount): static
    {
        $this->total_amount = $total_amount;

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
            $orderItem->setCommande($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getCommande() === $this) {
                $orderItem->setCommande(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayment(): Collection
    {
        return $this->payment;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payment->contains($payment)) {
            $this->payment->add($payment);
            $payment->setOrder($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payment->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getOrder() === $this) {
                $payment->setOrder(null);
            }
        }

        return $this;
    }

    //le nombre d'éléments de l'order
    public function getItemsCount(): int
    {
        return $this->orderItems->count();
    }
}
