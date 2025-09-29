<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AddressRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

use function sprintf;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['address:read', 'user:read', 'order:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 200)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $street = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $city = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 20)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $zipCode = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['address:read', 'address:write', 'user:read', 'order:read'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['address:read', 'address:write', 'user:read'])]
    private bool $isDefault = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['address:read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['address:read'])]
    private ?DateTimeImmutable $updatedAt = null;

    // Relations
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['address:read'])]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'shippingAddress')]
    private Collection $ordersAsShipping;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'billingAddress')]
    private Collection $ordersAsBilling;

    public function __construct()
    {
        $this->ordersAsShipping = new ArrayCollection();
        $this->ordersAsBilling = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getFormattedAddress();
    }

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

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getOrdersAsShipping(): Collection
    {
        return $this->ordersAsShipping;
    }

    public function addOrderAsShipping(Order $order): static
    {
        if (!$this->ordersAsShipping->contains($order)) {
            $this->ordersAsShipping->add($order);
            $order->setShippingAddress($this);
        }

        return $this;
    }

    public function removeOrderAsShipping(Order $order): static
    {
        if ($this->ordersAsShipping->removeElement($order)) {
            if ($order->getShippingAddress() === $this) {
                $order->setShippingAddress(null);
            }
        }

        return $this;
    }

    public function getOrdersAsBilling(): Collection
    {
        return $this->ordersAsBilling;
    }

    public function addOrderAsBilling(Order $order): static
    {
        if (!$this->ordersAsBilling->contains($order)) {
            $this->ordersAsBilling->add($order);
            $order->setBillingAddress($this);
        }

        return $this;
    }

    public function removeOrderAsBilling(Order $order): static
    {
        if ($this->ordersAsBilling->removeElement($order)) {
            if ($order->getBillingAddress() === $this) {
                $order->setBillingAddress(null);
            }
        }

        return $this;
    }

    public function getFormattedAddress(): string
    {
        return sprintf(
            '%s %s, %s, %s %s, %s',
            $this->firstName,
            $this->lastName,
            $this->street,
            $this->zipCode,
            $this->city,
            $this->country,
        );
    }
}
