<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Phone
{
    #[ORM\Column(name: 'phone', type: 'string', nullable: true)]
    private ?string $primary = null;

    #[ORM\Column(name: 'phone2', type: 'string', nullable: true)]
    private ?string $secondary = null;

    public function __construct(?string $primary = null, ?string $secondary = null)
    {
        $this->primary = $primary;
        $this->secondary = $secondary;
    }

    public function getPrimary(): ?string
    {
        return $this->primary;
    }

    public function setPrimary(?string $primary): self
    {
        $this->primary = $primary;

        return $this;
    }

    public function getSecondary(): ?string
    {
        return $this->secondary;
    }

    public function setSecondary(?string $secondary): self
    {
        $this->secondary = $secondary;

        return $this;
    }
}