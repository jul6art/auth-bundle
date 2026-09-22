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
     * application hashes it. __serialize() keeps it out of the session.
     */
    protected ?string $plainPassword = null;

    /**
     * What the session stores of this user — without the plain password, and with a checksum of the
     * hash instead of the hash.
     *
     * The session keeps the serialized user between requests: a plain password set during the same
     * request (a registration, a password change) would otherwise be written there in clear, and the
     * hash itself on every login. Symfony ≥ 7.3 compares a CRC32C of the hash when it refreshes the
     * user, so the checksum still logs the user out when the password changes.
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = get_mangled_object_vars($this);
        // Mangled names, as PHP's own serialization writes them: protected properties are keyed
        // "\0*\0<name>".
        $data["\0*\0plainPassword"] = null;
        // `?? ''`: the property is left uninitialised on a user that was never given a password.
        $data["\0*\0password"] = hash('crc32c', $this->password ?? '');

        return $data;
    }

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
     * Deprecated on `UserInterface` since Symfony 7.3, and gone from the interface entirely in
     * 8.0 — no `#[\Override]` here, or loading this class under Symfony 8 is a fatal error, not a
     * deprecation notice.
     *
     * `#[\Deprecated]` is what Symfony 7.3 asks for — it stops calling the method and stops warning
     * about it. The job it did, dropping the plain password, moved to {@see __serialize()}, the one
     * place where forgetting it would matter.
     */
    #[\Deprecated(message: 'since Symfony 7.3, credentials are erased by __serialize()')]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }
}
