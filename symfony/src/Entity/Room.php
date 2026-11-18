<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rooms')]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'room_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $capacity = 20;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, Equipment> */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Equipment::class)]
    private Collection $equipment;

    /** @var Collection<int, GymClass> */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: GymClass::class)]
    private Collection $classes;

    private ?int $equipmentCount = null;

    private ?int $classesCount = null;

    public function __construct()
    {
        $this->equipment = new ArrayCollection();
        $this->classes = new ArrayCollection();
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

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

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

    /**
     * @return Collection<int, Equipment>
     */
    public function getEquipment(): Collection
    {
        return $this->equipment;
    }

    /**
     * @return Collection<int, GymClass>
     */
    public function getClasses(): Collection
    {
        return $this->classes;
    }

    public function getEquipmentCount(): ?int
    {
        return $this->equipmentCount;
    }

    public function setEquipmentCount(?int $equipmentCount): void
    {
        $this->equipmentCount = $equipmentCount;
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
    public function toArray(bool $withEquipment = false, bool $withClasses = false): array
    {
        $data = [
            'room_id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'description' => $this->description,
        ];

        if (null !== $this->equipmentCount) {
            $data['equipment_count'] = $this->equipmentCount;
        }

        if (null !== $this->classesCount) {
            $data['classes_count'] = $this->classesCount;
        }

        if ($withEquipment) {
            $data['equipment'] = $this->equipment
                ->map(static fn (Equipment $item): array => $item->toArray())
                ->toArray();
        }

        if ($withClasses) {
            $data['classes'] = $this->classes
                ->map(static fn (GymClass $class): array => $class->toArray(withCoach: true))
                ->toArray();
        }

        return $data;
    }
}
