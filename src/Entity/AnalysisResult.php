<?php

namespace App\Entity;

use App\Repository\AnalysisResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AnalysisResultRepository::class)]
class AnalysisResult
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $source = null;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $analyzedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\ManyToOne(targetEntity: Target::class, inversedBy: 'analysisResults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Target $target = null;

    public function __construct()
    {
        $this->status = 'pending';
    }

    #[ORM\PrePersist]
    public function assignUuid(): void
    {
        if ($this->id === null) {
            $this->id = Uuid::v4();
        }
        if ($this->analyzedAt === null) {
            $this->analyzedAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(?Target $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-secondary',
            'success' => 'bg-success',
            'error' => 'bg-danger',
            'partial' => 'bg-warning',
            default => 'bg-secondary'
        };
    }
}