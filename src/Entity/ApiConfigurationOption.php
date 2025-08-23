<?php

namespace App\Entity;

use App\Repository\ApiConfigurationOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ApiConfigurationOptionRepository::class)]
class ApiConfigurationOption
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $optionName = null;

    #[ORM\Column(type: 'text')]
    private ?string $optionValue = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isEncrypted = false;

    #[ORM\ManyToOne(targetEntity: ApiConfiguration::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ApiConfiguration $apiConfiguration = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOptionName(): ?string
    {
        return $this->optionName;
    }

    public function setOptionName(string $optionName): static
    {
        $this->optionName = $optionName;
        return $this;
    }

    public function getOptionValue(): ?string
    {
        return $this->optionValue;
    }

    public function setOptionValue(string $optionValue): static
    {
        $this->optionValue = $optionValue;
        return $this;
    }

    public function isEncrypted(): bool
    {
        return $this->isEncrypted;
    }

    public function setIsEncrypted(bool $isEncrypted): static
    {
        $this->isEncrypted = $isEncrypted;
        return $this;
    }

    public function getApiConfiguration(): ?ApiConfiguration
    {
        return $this->apiConfiguration;
    }

    public function setApiConfiguration(?ApiConfiguration $apiConfiguration): static
    {
        $this->apiConfiguration = $apiConfiguration;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}