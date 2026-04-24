<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

use App\Repository\ObjectiveRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ObjectiveRepository::class)]
#[ORM\Table(name: 'objectives')]
class Objective
{
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;
    public const MAX_TEXT_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idOb = null;

    public function getIdOb(): ?int
    {
        return $this->idOb;
    }

    public function setIdOb(int $idOb): self
    {
        $this->idOb = $idOb;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Gedmo\Translatable]
    private ?string $descriptionOb = null;

    #[Assert\NotBlank(message: 'La description de l objectif est obligatoire.')]
    #[Assert\Length(
        max: self::MAX_TEXT_LENGTH,
        maxMessage: 'La description de l objectif ne doit pas depasser 255 caracteres.'
    )]
    public function getDescriptionOb(): ?string
    {
        return $this->descriptionOb;
    }

    public function setDescriptionOb(string $descriptionOb): self
    {
        $this->descriptionOb = $descriptionOb;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $priorityOb = null;

    #[Assert\NotNull(message: 'La priorite de l objectif est obligatoire.')]
    #[Assert\Choice(
        choices: [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT],
        message: 'La priorite selectionnee est invalide.'
    )]
    public function getPriorityOb(): ?int
    {
        return $this->priorityOb;
    }

    public function setPriorityOb(?int $priorityOb): self
    {
        $this->priorityOb = $priorityOb;
        return $this;
    }

    public function getPriorityLabel(): string
    {
        return match ($this->priorityOb) {
            self::PRIORITY_LOW => 'Basse',
            self::PRIORITY_MEDIUM => 'Moyenne',
            self::PRIORITY_HIGH => 'Haute',
            self::PRIORITY_URGENT => 'Urgente',
            default => 'Non definie',
        };
    }

    public function getPriorityCssClass(): string
    {
        return match ($this->priorityOb) {
            self::PRIORITY_LOW => 'low',
            self::PRIORITY_MEDIUM => 'medium',
            self::PRIORITY_HIGH => 'high',
            self::PRIORITY_URGENT => 'urgent',
            default => 'unknown',
        };
    }

    #[ORM\ManyToOne(targetEntity: Strategie::class, inversedBy: 'objectives')]
    #[ORM\JoinColumn(name: 'ids', referencedColumnName: 'idStrategie')]
    private ?Strategie $strategie = null;

    #[Assert\NotNull(message: 'La strategie associee est obligatoire.')]
    public function getStrategie(): ?Strategie
    {
        return $this->strategie;
    }

    public function setStrategie(?Strategie $strategie): self
    {
        $this->strategie = $strategie;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Gedmo\Translatable]
    private ?string $nomObj = null;

    #[Gedmo\Locale]
    private ?string $locale = null;

    #[Assert\NotBlank(message: 'Le nom de l objectif est obligatoire.')]
    #[Assert\Length(
        min: 3,
        minMessage: 'Le nom de l objectif doit contenir au moins 3 caracteres.',
        max: self::MAX_TEXT_LENGTH,
        maxMessage: 'Le nom de l objectif ne doit pas depasser 255 caracteres.'
    )]
    public function getNomObj(): ?string
    {
        return $this->nomObj;
    }

    public function setNomObj(?string $nomObj): self
    {
        $this->nomObj = $nomObj;
        return $this;
    }

    public function setTranslatableLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

}
