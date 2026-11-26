<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Assistance;

/**
 * An assisted session: someone signed in under an account to help its person — the controlled impersonation of the
 * provider a realm signs in through, whose identity token names them (`act`, RFC 8693). It ends at a set time, without
 * a refresh, and the account's password and second factor stay out of its reach.
 */
final readonly class Assistance
{
    /**
     * @param string $assistant the address of whoever acts, as the provider names them
     * @param string $name      their name
     */
    public function __construct(
        public string $assistant,
        public string $name,
        public \DateTimeImmutable $endsAt,
    ) {
    }

    /**
     * What the access token carries of it: whoever acts (`act`).
     *
     * @return array{email: string, name: string}
     */
    public function claim(): array
    {
        return ['email' => $this->assistant, 'name' => $this->name];
    }

    /**
     * Read back from an access token's payload: `act`, and its expiry.
     *
     * @param array<mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $act = $payload[Assistances::CLAIM] ?? null;
        $expires = $payload['exp'] ?? null;
        if (!\is_array($act) || !\is_string($act['email'] ?? null) || '' === $act['email'] || !\is_int($expires)) {
            return null;
        }

        return new self($act['email'], \is_string($act['name'] ?? null) ? $act['name'] : $act['email'], new \DateTimeImmutable('@'.$expires));
    }
}
