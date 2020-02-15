<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface {
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $username;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GitRepository", mappedBy="user", orphanRemoval=true)
     */
    private $repositories;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\GitRepository", mappedBy="collaborators")
     */
    private $gitRepositories;

    public function __construct() {
        $this->repositories = new ArrayCollection();
        $this->gitRepositories = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string {
        return (string)$this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string {
        return (string)$this->password;
    }

    public function setPassword(string $password): self {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt() {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials() {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection|GitRepository[]
     */
    public function getRepositories(): Collection {
        return $this->repositories;
    }

    public function addRepository(GitRepository $repository): self {
        if (!$this->repositories->contains($repository)) {
            $this->repositories[] = $repository;
            $repository->setUser($this);
        }

        return $this;
    }

    public function removeRepository(GitRepository $repository): self {
        if ($this->repositories->contains($repository)) {
            $this->repositories->removeElement($repository);
            // set the owning side to null (unless already changed)
            if ($repository->getUser() === $this) {
                $repository->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|GitRepository[]
     */
    public function getGitRepositories(): Collection
    {
        return $this->gitRepositories;
    }

    public function addGitRepository(GitRepository $gitRepository): self
    {
        if (!$this->gitRepositories->contains($gitRepository)) {
            $this->gitRepositories[] = $gitRepository;
            $gitRepository->addCollaborator($this);
        }

        return $this;
    }

    public function removeGitRepository(GitRepository $gitRepository): self
    {
        if ($this->gitRepositories->contains($gitRepository)) {
            $this->gitRepositories->removeElement($gitRepository);
            $gitRepository->removeCollaborator($this);
        }

        return $this;
    }
}
