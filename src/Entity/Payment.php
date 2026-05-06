<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payment')]
    private ?Order $order = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    private ?PaymentMethod $payment = null;

    #[ORM\Column(length: 255)]
    private ?string $transaction_reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transaction_status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getPayment(): ?PaymentMethod
    {
        return $this->payment;
    }

    public function setPayment(?PaymentMethod $payment): static
    {
        $this->payment = $payment;

        return $this;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transaction_reference;
    }

    public function setTransactionReference(string $transaction_reference): static
    {
        $this->transaction_reference = $transaction_reference;

        return $this;
    }

    public function getTransactionStatus(): ?string
    {
        return $this->transaction_status;
    }

    public function setTransactionStatus(?string $transaction_status): static
    {
        $this->transaction_status = $transaction_status;

        return $this;
    }
}
