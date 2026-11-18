<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\DateFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'classes')]
class GymClass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'class_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $title = '';

    #[ORM\ManyToOne(inversedBy: 'classes', targetEntity: Coach::class)]
    #[ORM\JoinColumn(name: 'coach_id', referencedColumnName: 'coach_id', nullable: true)]
    private ?Coach $coach = null;

    #[ORM\ManyToOne(inversedBy: 'classes', targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'room_id', referencedColumnName: 'room_id', nullable: true, onDelete: 'SET NULL')]
    private ?Room $room = null;

    #[ORM\Column(name: 'class_time', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $classTime = null;

    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $capacity = 20;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(mappedBy: 'gymClass', targetEntity: Registration::class)]
    private Collection $registrations;

    /** @var Collection<int, Attendance> */
    #[ORM\OneToMany(mappedBy: 'gymClass', targetEntity: Attendance::class)]
    private Collection $attendances;

    private ?int $membersCount = null;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
        $this->attendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCoach(): ?Coach
    {
        return $this->coach;
    }

    public function setCoach(?Coach $coach): self
    {
        $this->coach = $coach;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getClassTime(): ?\DateTimeImmutable
    {
        return $this->classTime;
    }

    public function setClassTime(\DateTimeImmutable $classTime): self
    {
        $this->classTime = $classTime;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
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
     * @return array<int, Member>
     */
    public function getMembers(): array
    {
        return $this->registrations
            ->map(static fn (Registration $registration): Member => $registration->getMember())
            ->toArray();
    }

    public function getMembersCount(): ?int
    {
        return $this->membersCount;
    }

    public function setMembersCount(?int $membersCount): void
    {
        $this->membersCount = $membersCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        bool $withCoach = false,
        bool $withRoom = false,
        bool $withMembers = false,
        bool $withAttendances = false,
    ): array {
        $data = [
            'class_id' => $this->id,
            'title' => $this->title,
            'coach_id' => $this->coach?->getId(),
            'room_id' => $this->room?->getId(),
            'class_time' => DateFormatter::dateTime($this->classTime),
            'capacity' => $this->capacity,
        ];

        if (null !== $this->membersCount) {
            $data['members_count'] = $this->membersCount;
        }

        if ($withCoach) {
            $data['coach'] = $this->coach?->toArray();
        }

        if ($withRoom) {
            $data['room'] = $this->room?->toArray();
        }

        if ($withMembers) {
            $data['members'] = array_map(
                static fn (Member $member): array => $member->toArray(),
                $this->getMembers(),
            );
        }

        if ($withAttendances) {
            $data['attendances'] = $this->attendances
                ->map(static fn (Attendance $attendance): array => $attendance->toArray())
                ->toArray();
        }

        return $data;
    }
}
