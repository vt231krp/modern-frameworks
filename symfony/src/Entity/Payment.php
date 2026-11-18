<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Util\DateFormatter;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'payments_method_index', columns: ['method'])]
#[ORM\Index(name: 'payments_status_index', columns: ['status'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'payment_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments', targetEntity: Subscription::class)]
    #[ORM\JoinColumn(name: 'sub_id', referencedColumnName: 'sub_id', nullable: false, onDelete: 'CASCADE')]
    private ?Subscription $subscription = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(name: 'payment_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $paymentDate = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PaymentMethod::class, options: ['default' => 'card'])]
    private PaymentMethod $method = PaymentMethod::Card;

    #[ORM\Column(type: 'string', length: 20, enumType: PaymentStatus::class, options: ['default' => 'pending'])]
    private PaymentStatus $status = PaymentStatus::Pending;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(null|string|int|float $amount): self
    {
        $this->amount = DateFormatter::decimal($amount) ?? '0.00';

        return $this;
    }

    public function getPaymentDate(): ?\DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): self
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withSubscription = false): array
    {
        $data = [
            'payment_id' => $this->id,
            'sub_id' => $this->subscription?->getId(),
            'amount' => DateFormatter::decimal($this->amount),
            'payment_date' => DateFormatter::date($this->paymentDate),
            'method' => $this->method->value,
            'status' => $this->status->value,
        ];

        if ($withSubscription) {
            $data['subscription'] = $this->subscription?->toArray(withMember: true);
        }

        return $data;
    }
}
