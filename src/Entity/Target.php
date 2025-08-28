<?php

namespace App\Entity;

use App\Repository\TargetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: TargetRepository::class)]
class Target
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 500)]
    private ?string $value = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAnalyzed = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $osintTools = null;

    #[ORM\ManyToOne(targetEntity: Investigation::class, inversedBy: 'targets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Investigation $investigation = null;

    #[ORM\OneToMany(mappedBy: 'target', targetEntity: AnalysisResult::class, cascade: ['persist', 'remove'])]
    private Collection $analysisResults;

    public function __construct()
    {
        $this->analysisResults = new ArrayCollection();
        $this->status = 'pending';
        $this->osintTools = [];
    }

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastAnalyzed(): ?\DateTimeImmutable
    {
        return $this->lastAnalyzed;
    }

    public function setLastAnalyzed(?\DateTimeImmutable $lastAnalyzed): static
    {
        $this->lastAnalyzed = $lastAnalyzed;
        return $this;
    }

    public function getOsintTools(): array
    {
        return $this->osintTools ?? [];
    }

    public function setOsintTools(?array $osintTools): static
    {
        $this->osintTools = $osintTools ?? [];
        return $this;
    }

    public function hasOsintTool(string $tool): bool
    {
        return in_array($tool, $this->osintTools ?? [], true);
    }

    public function addOsintTool(string $tool): static
    {
        if ($this->osintTools === null) {
            $this->osintTools = [];
        }
        if (!$this->hasOsintTool($tool)) {
            $this->osintTools[] = $tool;
        }
        return $this;
    }

    public function removeOsintTool(string $tool): static
    {
        if ($this->osintTools !== null) {
            $this->osintTools = array_values(array_filter(
                $this->osintTools, 
                fn($t) => $t !== $tool
            ));
        }
        return $this;
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

    public function getAnalysisResults(): Collection
    {
        return $this->analysisResults;
    }

    public function addAnalysisResult(AnalysisResult $analysisResult): static
    {
        if (!$this->analysisResults->contains($analysisResult)) {
            $this->analysisResults->add($analysisResult);
            $analysisResult->setTarget($this);
        }
        return $this;
    }

    public function removeAnalysisResult(AnalysisResult $analysisResult): static
    {
        if ($this->analysisResults->removeElement($analysisResult)) {
            if ($analysisResult->getTarget() === $this) {
                $analysisResult->setTarget(null);
            }
        }
        return $this;
    }

    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'ip' => 'bi-hdd-network',
            'url' => 'bi-link-45deg',
            'domain' => 'bi-globe',
            'email' => 'bi-envelope',
            'hash' => 'bi-fingerprint',
            'phone' => 'bi-telephone',
            'alias' => 'bi-person-badge',
            default => 'bi-question-circle'
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-secondary',
            'analyzing' => 'bg-info',
            'analyzed' => 'bg-success',
            'error' => 'bg-danger',
            default => 'bg-secondary'
        };
    }
}