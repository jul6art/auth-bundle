<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Test;

use Jul6Art\CoreBundle\Test\KernelConfiguration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The production firewalls hold their rules ({@see FirewallRules}): a throttled sign-in wherever a
 * person types a password, one account checker behind every way in, single-use hashed refresh
 * tokens, and a sign-out that only a POST triggers.
 *
 * A project extends it once, in `tests/Architecture/`:
 *
 * ```php
 * final class FirewallRulesTest extends FirewallRulesTestCase
 * {
 * }
 * ```
 *
 * The rules read the configuration the `prod` environment writes, without booting it (core-bundle's
 * `KernelConfiguration`); the sign-out routes are read from the project's router.
 */
abstract class FirewallRulesTestCase extends KernelTestCase
{
    public function testTheProductionFirewallsHoldTheirRules(): void
    {
        $configuration = KernelConfiguration::of(static::getKernelClass(), 'prod');
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        $logoutMethods = static function (string $path) use ($routes): ?array {
            $route = $routes->get($path);
            if (!$route instanceof Route) {
                foreach ($routes as $candidate) {
                    if ($candidate->getPath() === $path) {
                        $route = $candidate;
                        break;
                    }
                }
            }

            return $route?->getMethods();
        };

        $violations = FirewallRules::violations(
            $configuration->extension('security'),
            $configuration->has('gesdinet_jwt_refresh_token') ? $configuration->extension('gesdinet_jwt_refresh_token') : null,
            $logoutMethods,
            static::tokenFirewalls(),
        );

        self::assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * The firewalls whose custom authenticator checks a long random token — a runner's, a server
     * agent's, a personal API token — and not a password a person types: no guess to throttle, and
     * the authenticator itself decides what it accepts, so the shared account checker does not apply.
     * Override it to name one, with its reason next to it: the list is what a review sees.
     *
     * @return list<string>
     */
    protected static function tokenFirewalls(): array
    {
        return [];
    }
}
