<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\InheritanceType("JOINED")]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
abstract class Tab
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    protected ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    protected string $name;

    #[ORM\ManyToOne(targetEntity: Dashboard::class)]
    protected Dashboard $dashboard;

    #[ORM\Column]
    protected int $position;

    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;

    protected function __construct() {}

    public function initialize(string $name, Dashboard $dashboard, int $position): void
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->name = $name;
        $this->dashboard = $dashboard;
        $this->position = $position;
    }
}
