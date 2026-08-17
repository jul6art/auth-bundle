<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\DependencyInjection;

use Jul6Art\AuthBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsAuth(): void
    {
        self::assertSame('auth', new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
    }

    public function testItIsEnabledByDefault(): void
    {
        self::assertSame(['enabled' => true], $this->process([]));
    }

    public function testItCanBeDisabled(): void
    {
        self::assertSame(['enabled' => false], $this->process([['enabled' => false]]));
    }

    /**
     * enabled is a booleanNode, so it no longer silently accepts arbitrary scalars.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanValues(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['enabled' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [0];
        yield 'array' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
