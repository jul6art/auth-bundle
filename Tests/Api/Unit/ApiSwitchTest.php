<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Unit;

use Jul6Art\AuthBundle\Api\Realm\Realms;
use Jul6Art\AuthBundle\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * An application that installs the bundle without asking for the API sign-in gets none of it: no service, no
 * parameter, no dependency on the packages it would need.
 */
#[CoversNothing]
final class ApiSwitchTest extends AbstractFunctionalTestCase
{
    public function testTheApiSignInIsAbsentByDefault(): void
    {
        $container = $this->boot();

        self::assertFalse($container->has(Realms::class));
        self::assertFalse($container->hasParameter('auth.api.realms'));
    }
}
