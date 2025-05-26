<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

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

    public function initialize(string $name, Workspace $workspace): void
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
        $this->name = $name;
        $this->workspace = $workspace;
    }
}
