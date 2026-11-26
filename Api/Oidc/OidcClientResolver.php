<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Jul6Art\AuthBundle\Api\Realm\Realm;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tells a realm's client per request, instead of its configuration: a platform that hosts several applications, each
 * its own client of the provider. Autoconfigured; the first that answers wins, the configuration otherwise.
 */
#[AutoconfigureTag(self::TAG)]
interface OidcClientResolver
{
    public const string TAG = 'auth.oidc_client_resolver';

    public function client(Realm $realm, Request $request): ?OidcClient;
}
