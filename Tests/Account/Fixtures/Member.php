<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Account\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Util\Strings;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An application's account, as the skeleton's back-office writes it: names, an active flag, a username.
 */
#[ORM\Entity]
#[ORM\Table(name: 'member')]
class Member implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private string $firstName = '';

    #[ORM\Column]
    private string $lastName = '';

    #[ORM\Column]
    private bool $isActive = false;

    #[ORM\Column(nullable: true)]
    private ?string $username = null;

    #[ORM\Column(nullable: true)]
    private ?string $confirmedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = Strings::lowerEmail($email) ?? '';

        return $this;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : 'unknown';
    }

    #[\Override]
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getConfirmedBy(): ?string
    {
        return $this->confirmedBy;
    }

    public function confirmBy(string $listener): static
    {
        $this->confirmedBy = $listener;

        return $this;
    }

    /**
     * Still abstract on `UserInterface` under Symfony 7.4, gone in 8.0: no `#[\Override]`, as on the bundle's User.
     */
    #[\Deprecated(message: 'since Symfony 7.3, there are no credentials to erase')]
    public function eraseCredentials(): void
    {
    }
}
