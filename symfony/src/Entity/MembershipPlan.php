<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\DateFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'membership_plans')]
#[ORM\Index(name: 'membership_plans_is_active_index', columns: ['is_active'])]
class MembershipPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'plan_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(name: 'duration_days', type: 'integer')]
    private int $durationDays = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: Subscription::class)]
    private Collection $subscriptions;

    private ?int $subscriptionsCount = null;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): self
    {
        $this->durationDays = $durationDays;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(null|string|int|float $price): self
    {
        $this->price = DateFormatter::decimal($price) ?? '0.00';

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function getSubscriptionsCount(): ?int
    {
        return $this->subscriptionsCount;
    }

    public function setSubscriptionsCount(?int $subscriptionsCount): void
    {
        $this->subscriptionsCount = $subscriptionsCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withSubscriptions = false): array
    {
        $data = [
            'plan_id' => $this->id,
            'name' => $this->name,
            'duration_days' => $this->durationDays,
            'price' => DateFormatter::decimal($this->price),
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];

        if (null !== $this->subscriptionsCount) {
            $data['subscriptions_count'] = $this->subscriptionsCount;
        }

        if ($withSubscriptions) {
            $data['subscriptions'] = $this->subscriptions
                ->map(static fn (Subscription $subscription): array => $subscription->toArray(withMember: true))
                ->toArray();
        }

        return $data;
    }
}
