<?php

namespace App\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repositories\ResidenceRepository::class)]
#[ORM\Table(name: 'residences')]
class Residence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 150)]
    private string $nom;

    #[ORM\Column(type: 'string', length: 255)]
    private string $adresse;

    #[ORM\Column(type: 'integer')]
    private int $nombreLogements = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $salleClimatisee = false;

    #[ORM\Column(type: 'boolean')]
    private bool $pointFraicheur = false;

    #[ORM\ManyToOne(targetEntity: Quartier::class, inversedBy: 'residences')]
    #[ORM\JoinColumn(name: 'quartier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Quartier $quartier;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getAdresse(): string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getNombreLogements(): int
    {
        return $this->nombreLogements;
    }

    public function setNombreLogements(int $nombreLogements): static
    {
        $this->nombreLogements = $nombreLogements;

        return $this;
    }

    public function isSalleClimatisee(): bool
    {
        return $this->salleClimatisee;
    }

    public function setSalleClimatisee(bool $salleClimatisee): static
    {
        $this->salleClimatisee = $salleClimatisee;

        return $this;
    }

    public function isPointFraicheur(): bool
    {
        return $this->pointFraicheur;
    }

    public function setPointFraicheur(bool $pointFraicheur): static
    {
        $this->pointFraicheur = $pointFraicheur;

        return $this;
    }

    public function getQuartier(): Quartier
    {
        return $this->quartier;
    }

    public function setQuartier(Quartier $quartier): static
    {
        $this->quartier = $quartier;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTime();

        return $this;
    }
}
