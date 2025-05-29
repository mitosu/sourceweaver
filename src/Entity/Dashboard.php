<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\ValueObject\DashboardName;

#[ORM\Entity]
class Dashboard
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    private Workspace $workspace;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    protected function __construct() {}

    public function initialize(DashboardName $name, Workspace $workspace): void
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->name = (string) $name;
        $this->workspace = $workspace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function renameTo(DashboardName $newName): void
    {
        $this->name = (string) $newName;
    }
}
