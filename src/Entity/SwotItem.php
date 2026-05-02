<?php

namespace App\Entity;

use App\Entity\Trait\BlameableTrait;
use App\Repository\SwotItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: SwotItemRepository::class)]
#[ORM\Table(name: 'swot_item')]
#[ORM\HasLifecycleCallbacks]
class SwotItem
{
    use BlameableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Strategie::class, inversedBy: 'swotItems')]
    #[ORM\JoinColumn(name: 'strategie_id', referencedColumnName: 'idStrategie', nullable: false, onDelete: 'CASCADE')]
    private ?Strategie $strategie = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $type = '';

    #[ORM\Column(type: 'text', nullable: false)]
    #[Gedmo\Translatable]
    private string $description = '';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $weight = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $created_at;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getStrategie(): ?Strategie
    {
        return $this->strategie;
    }

    public function setStrategie(?Strategie $strategie): self
    {
        $this->strategie = $strategie;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getCreated_at(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdated_at(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();

        if (!isset($this->created_at)) {
            $this->created_at = $now;
        }

        if ($this->updated_at === null) {
            $this->updated_at = $now;
        }
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updated_at = new \DateTimeImmutable();
    }
}