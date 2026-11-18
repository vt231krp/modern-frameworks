<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttendanceStatus;
use App\Util\DateFormatter;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'attendances')]
#[ORM\UniqueConstraint(name: 'attendance_member_class_unique', columns: ['member_id', 'class_id'])]
#[ORM\Index(name: 'attendances_status_index', columns: ['status'])]
class Attendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'attendance_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attendances', targetEntity: Member::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'member_id', nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\ManyToOne(inversedBy: 'attendances', targetEntity: GymClass::class)]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'class_id', nullable: false, onDelete: 'CASCADE')]
    private ?GymClass $gymClass = null;

    #[ORM\Column(type: 'string', length: 20, enumType: AttendanceStatus::class, options: ['default' => 'present'])]
    private AttendanceStatus $status = AttendanceStatus::Present;

    #[ORM\Column(name: 'checked_in_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

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

    public function getGymClass(): ?GymClass
    {
        return $this->gymClass;
    }

    public function setGymClass(GymClass $gymClass): self
    {
        $this->gymClass = $gymClass;

        return $this;
    }

    public function getStatus(): AttendanceStatus
    {
        return $this->status;
    }

    public function setStatus(AttendanceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(?\DateTimeImmutable $checkedInAt): self
    {
        $this->checkedInAt = $checkedInAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withMember = false, bool $withClass = false, bool $withClassRelations = false): array
    {
        $data = [
            'attendance_id' => $this->id,
            'member_id' => $this->member?->getId(),
            'class_id' => $this->gymClass?->getId(),
            'status' => $this->status->value,
            'checked_in_at' => DateFormatter::dateTime($this->checkedInAt),
            'notes' => $this->notes,
        ];

        if ($withMember) {
            $data['member'] = $this->member?->toArray();
        }

        if ($withClass) {
            $data['gym_class'] = $this->gymClass?->toArray(
                withCoach: $withClassRelations,
                withRoom: $withClassRelations,
            );
        }

        return $data;
    }
}
