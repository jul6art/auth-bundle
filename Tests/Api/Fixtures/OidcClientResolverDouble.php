<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures;

use Jul6Art\AuthBundle\Api\Oidc\OidcClient;
use Jul6Art\AuthBundle\Api\Oidc\OidcClientResolver;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Symfony\Component\HttpFoundation\Request;

/**
 * A platform that hosts realm A tells its client per request, instead of its configuration: by default the provider
 * double's, none when the test says the platform does not know the application.
 */
final class OidcClientResolverDouble implements OidcClientResolver
{
    public static bool $knows = true;

    /** @var list<string> the realms it was asked about */
    public static array $asked = [];

    public static function forget(): void
    {
        self::$knows = true;
        self::$asked = [];
    }

    #[\Override]
    public function client(Realm $realm, Request $request): ?OidcClient
    {
        self::$asked[] = $realm->name;

        return self::$knows ? new OidcClient(OidcProviderDouble::ISSUER, OidcProviderDouble::CLIENT, OidcProviderDouble::SECRET) : null;
    }
}
