<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What the application's server asks of the provider: its discovery document and its keys — kept an hour —, and the
 * exchange of a code for the tokens. Reached at the issuer's address, or at the backchannel's — the issuer's host kept,
 * for a provider behind a shared web server.
 */
final readonly class OidcProvider
{
    private const int TTL = 3600;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     */
    public function discovery(OidcClient $client): array
    {
        return $this->cache->get('auth.oidc.discovery.'.$this->key($client), function (ItemInterface $item) use ($client): array {
            $item->expiresAfter(self::TTL);
            $document = $this->get($client, rtrim($client->issuer, '/').'/.well-known/openid-configuration');
            $authorization = $document['authorization_endpoint'] ?? null;
            $token = $document['token_endpoint'] ?? null;
            $keys = $document['jwks_uri'] ?? null;
            if (($document['issuer'] ?? null) !== $client->issuer || !\is_string($authorization) || !\is_string($token) || !\is_string($keys)) {
                throw new OidcRefusal('auth.oidc.unavailable', 'a discovery document that is not the issuer\'s');
            }

            return ['authorization_endpoint' => $authorization, 'token_endpoint' => $token, 'jwks_uri' => $keys];
        });
    }

    /**
     * The provider's keys; asked again when a token names one not known yet — the provider changed its pair.
     *
     * @return list<array<string, mixed>>
     */
    public function keys(OidcClient $client, bool $fresh = false): array
    {
        $key = 'auth.oidc.keys.'.$this->key($client);
        if ($fresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item) use ($client): array {
            $item->expiresAfter(self::TTL);
            $keys = $this->get($client, $this->discovery($client)['jwks_uri'])['keys'] ?? null;
            if (!\is_array($keys)) {
                throw new OidcRefusal('auth.oidc.unavailable', 'no keys');
            }

            $published = [];
            foreach ($keys as $jwk) {
                if (\is_array($jwk)) {
                    $published[] = self::named($jwk);
                }
            }

            return $published;
        });
    }

    /**
     * The code exchanged for the tokens, with the verifier of its PKCE challenge.
     *
     * @return array<string, mixed>
     */
    public function token(OidcClient $client, string $code, string $redirectUri, #[\SensitiveParameter] string $verifier): array
    {
        [$url, $headers] = $this->reach($client, $this->discovery($client)['token_endpoint']);
        try {
            $response = $this->http->request('POST', $url, ['headers' => $headers + ['Accept' => 'application/json'], 'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $verifier,
                'client_id' => $client->clientId,
                'client_secret' => $client->clientSecret,
            ]]);
            if (200 !== $response->getStatusCode()) {
                throw new OidcRefusal('auth.oidc.invalid', 'the provider refused the code: HTTP '.$response->getStatusCode());
            }

            return self::named($response->toArray());
        } catch (ExceptionInterface $exception) {
            throw new OidcRefusal('auth.oidc.unavailable', 'its token endpoint', $exception);
        }
    }

    /**
     * A document of the provider, its keys as names.
     *
     * @param array<mixed> $document
     *
     * @return array<string, mixed>
     */
    private static function named(array $document): array
    {
        $named = [];
        foreach ($document as $name => $value) {
            $named[(string) $name] = $value;
        }

        return $named;
    }

    /**
     * @return array<mixed>
     */
    private function get(OidcClient $client, string $url): array
    {
        [$url, $headers] = $this->reach($client, $url);
        try {
            return $this->http->request('GET', $url, ['headers' => $headers + ['Accept' => 'application/json']])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new OidcRefusal('auth.oidc.unavailable', $url, $exception);
        }
    }

    /**
     * Where the application's server reaches an endpoint: at the backchannel's origin when there is one, the issuer's
     * host kept as `Host`.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function reach(OidcClient $client, string $url): array
    {
        if (null === $client->backchannel || '' === $client->backchannel) {
            return [$url, []];
        }
        $endpoint = parse_url($url);
        $back = parse_url($client->backchannel);
        if (!\is_array($endpoint) || !\is_array($back) || !isset($endpoint['host'], $back['scheme'], $back['host'])) {
            return [$url, []];
        }
        $reached = $back['scheme'].'://'.$back['host'].(isset($back['port']) ? ':'.$back['port'] : '').($endpoint['path'] ?? '').(isset($endpoint['query']) ? '?'.$endpoint['query'] : '');

        return [$reached, ['Host' => $endpoint['host'].(isset($endpoint['port']) ? ':'.$endpoint['port'] : '')]];
    }

    private function key(OidcClient $client): string
    {
        return hash('sha256', $client->issuer."\n".($client->backchannel ?? ''));
    }
}
