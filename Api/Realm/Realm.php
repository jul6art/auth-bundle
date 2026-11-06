<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Realm;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Account\LoginRecordInterface;

/**
 * Where one application's accounts sign in: its firewalls, its classes, the name its second factor and its
 * e-mails carry. A kernel that serves several applications — each on its own host, each with its own base —
 * declares one realm per application: an account, a refresh token, a reset link of one realm never opens
 * another.
 */
final readonly class Realm
{
    /**
     * @param list<string>                        $firewalls                 the firewalls of the realm's sign-in, its public routes and its API
     * @param class-string<ApiAccountInterface>   $userClass
     * @param class-string<LoginRecordInterface>  $loginRecordClass
     * @param class-string<RefreshTokenInterface> $refreshTokenClass         a gesdinet refresh token entity
     * @param class-string                        $resetPasswordRequestClass a symfonycasts reset request entity, whose repository implements its interface
     * @param array<string, string>               $resetLinks                the reset screen by language, `{token}` in place of the token
     */
    public function __construct(
        public string $name,
        public array $firewalls,
        public string $userClass,
        public string $loginRecordClass,
        public string $refreshTokenClass,
        public string $resetPasswordRequestClass,
        public string $issuer,
        public string $mailFrom,
        public array $resetLinks,
        public string $htmlTemplate,
        public string $textTemplate,
        public string $subject,
        public string $translationDomain,
    ) {
    }

    /**
     * The link of a reset e-mail, in the account's language — or the first language the realm declares.
     */
    public function resetLink(string $locale, string $token): string
    {
        $pattern = $this->resetLinks[$locale] ?? array_values($this->resetLinks)[0] ?? '';

        return str_replace('{token}', rawurlencode($token), $pattern);
    }

    public function owns(object $account): bool
    {
        return $account instanceof $this->userClass;
    }
}
