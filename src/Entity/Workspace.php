<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\User;
use App\Entity\ValueObject\WorkspaceName;

#[ORM\Entity]
class Workspace
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $owner = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    protected function __construct() {}

    public function initialize(WorkspaceName $name, User $owner): void
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->name = (string) $name;
        $this->owner = $owner;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getOwner(): ?User
    {
        return $this->owner;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function renameTo(WorkspaceName $newName): void
    {
        $this->name = (string) $newName;
    }
}
