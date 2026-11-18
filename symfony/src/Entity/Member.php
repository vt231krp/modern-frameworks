<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\DateFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'members')]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'member_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'member', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'join_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $joinDate = null;

    /** @var Collection<int, Subscription> */
    #[ORM\OneToMany(mappedBy: 'member', targetEntity: Subscription::class)]
    private Collection $subscriptions;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(mappedBy: 'member', targetEntity: Registration::class)]
    private Collection $registrations;

    /** @var Collection<int, Attendance> */
    #[ORM\OneToMany(mappedBy: 'member', targetEntity: Attendance::class)]
    private Collection $attendances;

    private ?int $subscriptionsCount = null;

    private ?int $classesCount = null;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->registrations = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getJoinDate(): ?\DateTimeImmutable
    {
        return $this->joinDate;
    }

    public function setJoinDate(?\DateTimeImmutable $joinDate): self
    {
        $this->joinDate = $joinDate;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    /**
     * Classes the member is registered for (mirrors Laravel's belongsToMany).
     *
     * @return array<int, GymClass>
     */
    public function getClasses(): array
    {
        return $this->registrations
            ->map(static fn (Registration $registration): GymClass => $registration->getGymClass())
            ->toArray();
    }

    public function getSubscriptionsCount(): ?int
    {
        return $this->subscriptionsCount;
    }

    public function setSubscriptionsCount(?int $subscriptionsCount): void
    {
        $this->subscriptionsCount = $subscriptionsCount;
    }

    public function getClassesCount(): ?int
    {
        return $this->classesCount;
    }

    public function setClassesCount(?int $classesCount): void
    {
        $this->classesCount = $classesCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withSubscriptions = false, bool $withClasses = false): array
    {
        $data = [
            'member_id' => $this->id,
            'user_id' => $this->user?->getId(),
            'name' => $this->name,
            'phone' => $this->phone,
            'join_date' => DateFormatter::date($this->joinDate),
        ];

        if (null !== $this->subscriptionsCount) {
            $data['subscriptions_count'] = $this->subscriptionsCount;
        }

        if (null !== $this->classesCount) {
            $data['classes_count'] = $this->classesCount;
        }

        if ($withSubscriptions) {
            $data['subscriptions'] = $this->subscriptions
                ->map(static fn (Subscription $subscription): array => $subscription->toArray())
                ->toArray();
        }

        if ($withClasses) {
            $data['classes'] = array_map(
                static fn (GymClass $class): array => $class->toArray(),
                $this->getClasses(),
            );
        }

        return $data;
    }
}
