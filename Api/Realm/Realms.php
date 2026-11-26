<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Realm;

use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Account\LoginRecordInterface;
use Jul6Art\AuthBundle\Api\Oidc\OidcSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The realms of the kernel, and the one a request belongs to: the realm that lists the firewall the request
 * passed through. A request outside every realm — another application's host, the command line — has none,
 * and the sign-in answers it nothing.
 *
 * @phpstan-type RealmConfig array{
 *     firewalls: list<string>,
 *     user_class: class-string<ApiAccountInterface>,
 *     login_record_class: class-string<LoginRecordInterface>,
 *     refresh_token_class: class-string<RefreshTokenInterface>,
 *     reset_password_request_class: class-string,
 *     issuer: string,
 *     mail: array{from: string, reset_links: array<string, string>, html_template: string, text_template: string, subject: string, translation_domain: string},
 *     oidc?: array{enabled: bool, issuer: string|null, client_id: string|null, client_secret: string|null, backchannel: string|null, after_sign_in: string, sign_in_page: string, methods: list<string>},
 * }
 */
final class Realms
{
    /** @var array<string, Realm> */
    private array $realms = [];

    /**
     * @param array<string, RealmConfig> $config
     */
    public function __construct(
        #[Autowire(param: 'auth.api.realms')]
        array $config,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
        foreach ($config as $name => $realm) {
            $this->realms[$name] = new Realm(
                $name,
                $realm['firewalls'],
                $realm['user_class'],
                $realm['login_record_class'],
                $realm['refresh_token_class'],
                $realm['reset_password_request_class'],
                $realm['issuer'],
                $realm['mail']['from'],
                $realm['mail']['reset_links'],
                $realm['mail']['html_template'],
                $realm['mail']['text_template'],
                $realm['mail']['subject'],
                $realm['mail']['translation_domain'],
                true === ($realm['oidc']['enabled'] ?? false) ? new OidcSettings(
                    $realm['oidc']['issuer'],
                    $realm['oidc']['client_id'],
                    $realm['oidc']['client_secret'],
                    $realm['oidc']['backchannel'],
                    $realm['oidc']['after_sign_in'],
                    $realm['oidc']['sign_in_page'],
                    $realm['oidc']['methods'],
                ) : null,
            );
        }
    }

    /**
     * The realm of the main request's firewall, if any.
     */
    public function current(): ?Realm
    {
        $request = $this->requestStack->getMainRequest();
        $firewall = null === $request ? null : $this->security->getFirewallConfig($request)?->getName();
        if (null === $firewall) {
            return null;
        }

        foreach ($this->realms as $realm) {
            if (\in_array($firewall, $realm->firewalls, true)) {
                return $realm;
            }
        }

        return null;
    }

    /**
     * The realm whose account class this account is.
     */
    public function of(object $account): ?Realm
    {
        foreach ($this->realms as $realm) {
            if ($realm->owns($account)) {
                return $realm;
            }
        }

        return null;
    }

    /**
     * @return array<string, Realm>
     */
    public function all(): array
    {
        return $this->realms;
    }
}
