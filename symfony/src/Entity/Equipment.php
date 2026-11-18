<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EquipmentCondition;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'equipment')]
#[ORM\Index(name: 'equipment_condition_index', columns: ['condition'])]
class Equipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'equipment_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(type: 'string', length: 20, enumType: EquipmentCondition::class, options: ['default' => 'good'])]
    private EquipmentCondition $condition = EquipmentCondition::Good;

    #[ORM\ManyToOne(inversedBy: 'equipment', targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'room_id', referencedColumnName: 'room_id', nullable: true, onDelete: 'SET NULL')]
    private ?Room $room = null;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCondition(): EquipmentCondition
    {
        return $this->condition;
    }

    public function setCondition(EquipmentCondition $condition): self
    {
        $this->condition = $condition;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withRoom = false): array
    {
        $data = [
            'equipment_id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'condition' => $this->condition->value,
            'room_id' => $this->room?->getId(),
        ];

        if ($withRoom) {
            $data['room'] = $this->room?->toArray();
        }

        return $data;
    }
}
