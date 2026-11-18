<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\DateFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'subscriptions_plan_id_index', columns: ['plan_id'])]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'sub_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions', targetEntity: Member::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'member_id', nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions', targetEntity: MembershipPlan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'plan_id', nullable: true, onDelete: 'SET NULL')]
    private ?MembershipPlan $plan = null;

    #[ORM\Column(name: 'start_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'end_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(mappedBy: 'subscription', targetEntity: Payment::class)]
    private Collection $payments;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getPlan(): ?MembershipPlan
    {
        return $this->plan;
    }

    public function setPlan(?MembershipPlan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(null|string|int|float $price): self
    {
        $this->price = DateFormatter::decimal($price);

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withMember = false, bool $withPlan = false, bool $withPayments = false): array
    {
        $data = [
            'sub_id' => $this->id,
            'member_id' => $this->member?->getId(),
            'plan_id' => $this->plan?->getId(),
            'start_date' => DateFormatter::date($this->startDate),
            'end_date' => DateFormatter::date($this->endDate),
            'price' => DateFormatter::decimal($this->price),
        ];

        if ($withMember) {
            $data['member'] = $this->member?->toArray();
        }

        if ($withPlan) {
            $data['plan'] = $this->plan?->toArray();
        }

        if ($withPayments) {
            $data['payments'] = $this->payments
                ->map(static fn (Payment $payment): array => $payment->toArray())
                ->toArray();
        }

        return $data;
    }
}
