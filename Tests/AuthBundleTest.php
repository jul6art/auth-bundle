<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests;

use Jul6Art\AuthBundle\AuthBundle;
use Jul6Art\AuthBundle\DependencyInjection\AuthExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthBundle::class)]
final class AuthBundleTest extends TestCase
{
    public function testItResolvesTheAuthExtensionByConvention(): void
    {
        $extension = new AuthBundle()->getContainerExtension();

        self::assertInstanceOf(AuthExtension::class, $extension);
        self::assertSame('auth', $extension->getAlias());
    }

    public function testItsPathPointsAtTheBundleRoot(): void
    {
        $bundle = new AuthBundle();

        self::assertSame('AuthBundle', $bundle->getName());
        self::assertFileExists($bundle->getPath().'/Resources/config/services.yaml');
    }
}
