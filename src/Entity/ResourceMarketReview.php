<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ResourceMarketReviewRepository;

#[ORM\Entity(repositoryClass: ResourceMarketReviewRepository::class)]
#[ORM\Table(name: 'resource_market_review')]
class ResourceMarketReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idReview = null;

    public function getIdReview(): ?int
    {
        return $this->idReview;
    }

    public function setIdReview(int $idReview): self
    {
        $this->idReview = $idReview;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $idListing = null;

    public function getIdListing(): ?int
    {
        return $this->idListing;
    }

    public function setIdListing(int $idListing): self
    {
        $this->idListing = $idListing;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $stars = null;

    public function getStars(): ?int
    {
        return $this->stars;
    }

    public function setStars(int $stars): self
    {
        $this->stars = $stars;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $comment = null;

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $createdAt = null;

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $idOrder = null;

    public function getIdOrder(): ?int
    {
        return $this->idOrder;
    }

    public function setIdOrder(?int $idOrder): self
    {
        $this->idOrder = $idOrder;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reviewerUserId = null;

    public function getReviewerUserId(): ?int
    {
        return $this->reviewerUserId;
    }

    public function setReviewerUserId(?int $reviewerUserId): self
    {
        $this->reviewerUserId = $reviewerUserId;
        return $this;
    }

}
