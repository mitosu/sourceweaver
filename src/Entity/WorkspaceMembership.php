<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class WorkspaceMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $role;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    protected function __construct() {}

    public function initialize(Workspace $workspace, User $user, string $role): void
    {
        $this->id = Uuid::v4();
        $this->joinedAt = new \DateTimeImmutable();
        $this->workspace = $workspace;
        $this->user = $user;
        $this->role = $role;
    }
}
