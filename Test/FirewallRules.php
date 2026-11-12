<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Test;

/**
 * What the firewalls of a production configuration must say, as rules over the raw `security` and
 * `gesdinet_jwt_refresh_token` configurations. Each violation is a sentence that says what is
 * wrong and what it opens.
 */
final class FirewallRules
{
    /** The authenticators that check a secret a person types: each must be throttled. */
    private const array TYPED_CREDENTIALS = ['form_login', 'json_login', 'form_login_ldap', 'json_login_ldap', 'custom_authenticator', 'custom_authenticators'];

    /** Every way a firewall authenticates: the account checker must stand behind all of them. */
    private const array AUTHENTICATORS = [...self::TYPED_CREDENTIALS, 'jwt', 'refresh_jwt', 'remember_me', 'login_link', 'access_token', 'http_basic'];

    /**
     * @param array<string, mixed>            $security       the raw `security` configuration
     * @param array<string, mixed>|null       $refreshTokens  the raw `gesdinet_jwt_refresh_token` configuration, null when the bundle is absent
     * @param callable(string): ?list<string> $logoutMethods  the methods of the route a logout path names (empty: any), null when no route has it
     * @param list<string>                    $tokenFirewalls firewalls whose authenticator checks a long random token, not a typed password: exempt from throttling only
     *
     * @return list<string>
     */
    public static function violations(array $security, ?array $refreshTokens, callable $logoutMethods, array $tokenFirewalls = []): array
    {
        $violations = [];
        $firewalls = self::firewalls($security);

        foreach ($firewalls as $name => $firewall) {
            $typed = array_values(array_intersect(self::TYPED_CREDENTIALS, array_keys($firewall)));
            if ([] !== $typed && !\array_key_exists('login_throttling', $firewall) && !\in_array($name, $tokenFirewalls, true)) {
                $violations[] = \sprintf('Firewall "%s" checks a typed password (%s) without login_throttling: guesses are unlimited.', $name, implode(', ', $typed));
            }
        }

        // The account checker, compared between the firewalls that share the same accounts: a hosted
        // application with accounts of its own (its own provider) answers for its own checker. A token
        // firewall stands behind it too: when its token acts as an account (a personal API token), a
        // refusal the checker learns later applies to it without anyone thinking of it; when it is a
        // machine's, the checker lets it through.
        $checkersByProvider = [];
        foreach ($firewalls as $name => $firewall) {
            if ([] !== array_intersect(self::AUTHENTICATORS, array_keys($firewall))) {
                $provider = \is_string($firewall['provider'] ?? null) ? $firewall['provider'] : '(default)';
                $checkersByProvider[$provider][$name] = \is_string($firewall['user_checker'] ?? null) ? $firewall['user_checker'] : null;
            }
        }
        foreach ($checkersByProvider as $checkers) {
            $declared = array_values(array_unique(array_filter($checkers)));
            if ([] === $declared) {
                continue;
            }
            foreach ($checkers as $name => $checker) {
                if ($checker !== $declared[0] || \count($declared) > 1) {
                    $violations[] = \sprintf('Firewall "%s" uses user checker %s where another firewall of the same accounts uses %s: an account refused on one signs in through the other.', $name, $checker ?? 'none', $declared[0]);
                }
            }
        }

        if (null !== $refreshTokens) {
            if (true !== ($refreshTokens['single_use'] ?? null)) {
                $violations[] = 'gesdinet_jwt_refresh_token.single_use is not true: a stolen refresh token keeps working after its owner used it.';
            }
            $hash = $refreshTokens['hash_tokens'] ?? null;
            if (!\is_array($hash) || true !== ($hash['enabled'] ?? null)) {
                $violations[] = 'gesdinet_jwt_refresh_token.hash_tokens is not enabled: a copy of the table hands out working credentials.';
            }
        }

        foreach ($firewalls as $name => $firewall) {
            if (!\array_key_exists('logout', $firewall) || false === $firewall['logout']) {
                continue;
            }
            $logout = $firewall['logout'];
            $path = \is_array($logout) && \is_string($logout['path'] ?? null) ? $logout['path'] : '/logout';
            $methods = $logoutMethods($path);
            if (null === $methods) {
                $violations[] = \sprintf('Firewall "%s" signs out on "%s", which no route declares: any method signs out, a link or a prefetch included. Declare the route, POST only.', $name, $path);
            } elseif ([] === $methods || \in_array('GET', $methods, true)) {
                $violations[] = \sprintf('Firewall "%s" signs out on "%s", whose route answers GET: a third-party link or a prefetch signs the user out. Make it POST only.', $name, $path);
            }
        }

        return $violations;
    }

    /**
     * The firewalls that guard something: `security: false` ones (assets, profiler) are left out.
     *
     * @param array<string, mixed> $security
     *
     * @return array<string, array<string, mixed>>
     */
    private static function firewalls(array $security): array
    {
        $firewalls = [];
        $configured = $security['firewalls'] ?? [];
        foreach (\is_array($configured) ? $configured : [] as $name => $firewall) {
            if (!\is_string($name) || !\is_array($firewall) || false === ($firewall['security'] ?? true)) {
                continue;
            }
            $keys = [];
            foreach ($firewall as $key => $value) {
                if (\is_string($key)) {
                    $keys[$key] = $value;
                }
            }
            $firewalls[$name] = $keys;
        }

        return $firewalls;
    }
}
