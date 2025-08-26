<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Investigation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Investigation $investigation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $action = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $entityId = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function assignUuid(): void
    {
        if ($this->id === null) {
            $this->id = Uuid::v4();
        }
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInvestigation(): ?Investigation
    {
        return $this->investigation;
    }

    public function setInvestigation(?Investigation $investigation): static
    {
        $this->investigation = $investigation;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?Uuid
    {
        return $this->entityId;
    }

    public function setEntityId(?Uuid $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getActionIcon(): string
    {
        return match ($this->action) {
            'investigation_created' => 'bi-plus-circle',
            'investigation_updated' => 'bi-pencil-square',
            'target_created' => 'bi-bullseye',
            'target_updated' => 'bi-pencil',
            'target_deleted' => 'bi-trash',
            'analysis_started' => 'bi-play-circle',
            'analysis_completed' => 'bi-check-circle',
            'analysis_failed' => 'bi-x-circle',
            'bulk_import' => 'bi-list-ul',
            default => 'bi-info-circle'
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'investigation_created', 'target_created' => 'success',
            'investigation_updated', 'target_updated' => 'info',
            'target_deleted' => 'danger',
            'analysis_started' => 'warning',
            'analysis_completed' => 'primary',
            'analysis_failed' => 'danger',
            'bulk_import' => 'info',
            default => 'secondary'
        };
    }
}