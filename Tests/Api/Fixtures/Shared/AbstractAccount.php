<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures\Shared;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Account\TwoFactorAccountTrait;

/**
 * An account of a test application: what an application declares on its own entity.
 */
#[ORM\MappedSuperclass]
abstract class AbstractAccount implements ApiAccountInterface
{
    use TwoFactorAccountTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 2)]
        private string $locale = 'en',
    ) {
    }

    /**
     * What the sign-in session keeps between the password and the second factor: never the TOTP secret.
     *
     * @return array{id: int|null, email: string, password: string}
     */
    public function __serialize(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'password' => hash('crc32c', (string) $this->password)];
    }

    /**
     * @param array{id: int|null, email: string, password: string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->password = $data['password'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getEmail(): string
    {
        return $this->email;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('An account without an address cannot be identified.');
        }

        return $this->email;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * Still on `UserInterface` in Symfony 7.4 — the lowest version this bundle supports — and gone in 8.0:
     * no `#[\Override]`, or loading this class under Symfony 8 is a fatal error. Nothing to erase: the
     * account keeps no plain password.
     */
    #[\Deprecated(message: 'since Symfony 7.3, credentials are erased by __serialize()')]
    public function eraseCredentials(): void
    {
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    #[\Override]
    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;

        return $this;
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    #[\Override]
    public function getPreferredLocale(): string
    {
        return $this->locale;
    }
}
