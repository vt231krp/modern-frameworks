<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'coaches')]
class Coach
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'coach_id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    /** @var Collection<int, GymClass> */
    #[ORM\OneToMany(mappedBy: 'coach', targetEntity: GymClass::class)]
    private Collection $classes;

    private ?int $classesCount = null;

    public function __construct()
    {
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * @return Collection<int, GymClass>
     */
    public function getClasses(): Collection
    {
        return $this->classes;
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
    public function toArray(bool $withClasses = false): array
    {
        $data = [
            'coach_id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
        ];

        if (null !== $this->classesCount) {
            $data['classes_count'] = $this->classesCount;
        }

        if ($withClasses) {
            $data['classes'] = $this->classes
                ->map(static fn (GymClass $class): array => $class->toArray())
                ->toArray();
        }

        return $data;
    }
}
