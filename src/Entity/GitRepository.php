<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\GitRepositoryRepository")
 * @UniqueEntity(fields={"repoName"}, message="Ce repo existe déjà")
 */
class GitRepository {
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Type(type="alnum")
     * @Assert\NotBlank
     */
    private $repoName;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="repositories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="boolean")
     */
    private $private = false;

    public function __toString() : string
    {
        return $this->repoName;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getRepoName(): ?string {
        return $this->repoName;
    }

    public function setRepoName(string $name): self {
        $this->repoName = $name;

        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;

        return $this;
    }

    public function getPrivate(): ?bool {
        return $this->private;
    }

    public function setPrivate(bool $private): self {
        $this->private = $private;

        return $this;
    }
}
