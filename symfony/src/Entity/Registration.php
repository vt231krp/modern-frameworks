<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'registrations')]
class Registration
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'registrations', targetEntity: Member::class)]
    #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'member_id', nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'registrations', targetEntity: GymClass::class)]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'class_id', nullable: false, onDelete: 'CASCADE')]
    private ?GymClass $gymClass = null;

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getGymClass(): GymClass
    {
        return $this->gymClass;
    }

    public function setGymClass(GymClass $gymClass): self
    {
        $this->gymClass = $gymClass;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withMember = false, bool $withClass = false): array
    {
        $data = [
            'member_id' => $this->member?->getId(),
            'class_id' => $this->gymClass?->getId(),
        ];

        if ($withMember) {
            $data['member'] = $this->member?->toArray();
        }

        if ($withClass) {
            $data['gym_class'] = $this->gymClass?->toArray();
        }

        return $data;
    }
}
