<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Entity\Interfaces\UserInterface;
use Jul6Art\AuthBundle\Repository\UserRepository;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface
{
    use IdTrait;

    /**
     * Every user carries this role implicitly: getRoles() adds it and it is never
     * stored, so applications do not have to hard code the string.
     */
    public const string ROLE_USER = 'ROLE_USER';

    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    /**
     * Left uninitialised rather than nullable so the property type matches the
     * NOT NULL column; the getters use "??" which does not trip on that.
     */
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    protected string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    protected array $roles = [];

    /**
     * The hashed password.
     */
    #[ORM\Column(type: Types::STRING)]
    protected string $password;

    /**
     * Plain password, never persisted: it carries what a form collected until the
     * application hashes it. eraseCredentials() clears it.
     */
    protected ?string $plainPassword = null;

    public function getEmail(): ?string
    {
        return $this->email ?? null;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * Replaces getUsername(), dropped from Symfony's UserInterface in 6.0. The
     * interface documents a non-empty return, hence the guard.
     *
     * @throws \LogicException if the user carries no email yet
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        $email = $this->email ?? '';

        if ('' === $email) {
            throw new \LogicException('The user has no email, so it cannot be identified.');
        }

        return $email;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->password ?? '';
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * Still required by Symfony's UserInterface in 7.4 though deprecated since 7.3.
     * It has a job here: dropping the plain password once it has been hashed.
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }
}
