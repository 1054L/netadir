<?php

namespace App\Entity;

use App\Repository\CampaignResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignResultRepository::class)]
class CampaignResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'CampaignResult')]
    private ?Campaign $campaign = null;

    #[ORM\ManyToOne(inversedBy: 'CampaignResult')]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $isSent = null;

    #[ORM\Column]
    private ?bool $isOpened = null;

    #[ORM\Column]
    private ?bool $isClicked = null;

    #[ORM\Column]
    private ?bool $isCompromised = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): static
    {
        $this->campaign = $campaign;

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

    public function isSent(): ?bool
    {
        return $this->isSent;
    }

    public function setIsSent(bool $isSent): static
    {
        $this->isSent = $isSent;

        return $this;
    }

    public function isOpened(): ?bool
    {
        return $this->isOpened;
    }

    public function setIsOpened(bool $isOpened): static
    {
        $this->isOpened = $isOpened;

        return $this;
    }

    public function isClicked(): ?bool
    {
        return $this->isClicked;
    }

    public function setIsClicked(bool $isClicked): static
    {
        $this->isClicked = $isClicked;

        return $this;
    }

    public function isCompromised(): ?bool
    {
        return $this->isCompromised;
    }

    public function setIsCompromised(bool $isCompromised): static
    {
        $this->isCompromised = $isCompromised;

        return $this;
    }
}
