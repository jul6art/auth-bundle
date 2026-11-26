<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Jul6Art\AuthBundle\Api\Realm\Realm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tells a realm's client per request, instead of its configuration: a platform that hosts several applications, each
 * its own client of the provider. Autoconfigured — by the bundle's extension: an attribute on this interface would only
 * be read where the interface itself is scanned —; the first that answers wins, the configuration otherwise.
 */
interface OidcClientResolver
{
    public const string TAG = 'auth.oidc_client_resolver';

    public function client(Realm $realm, Request $request): ?OidcClient;
}
