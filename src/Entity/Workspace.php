<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\User;

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

    public function initialize(string $name, User $owner): void
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->name = $name;
        $this->owner = $owner;
    }
}
