<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Account;

use Jul6Art\AuthBundle\Account\ResponseFloor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The floor under an answer that must not tell, by its duration, what it found.
 */
#[CoversClass(ResponseFloor::class)]
final class ResponseFloorTest extends TestCase
{
    public function testAnInstantWorkTakesTheFloorAndGivesItsResult(): void
    {
        $startedAt = hrtime(true);
        $result = new ResponseFloor(60)->hold(static fn (): string => 'done');

        self::assertSame('done', $result);
        self::assertGreaterThanOrEqual(60_000_000, hrtime(true) - $startedAt);
    }

    public function testAWorkThatThrowsTakesTheFloorToo(): void
    {
        $startedAt = hrtime(true);

        try {
            new ResponseFloor(60)->hold(static function (): never {
                throw new \RuntimeException('The transport is down.');
            });
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertGreaterThanOrEqual(60_000_000, hrtime(true) - $startedAt, 'A failure never answers sooner than a success.');
        self::assertSame('The transport is down.', $thrown->getMessage(), 'The exception of the work goes through.');
    }

    public function testAWorkLongerThanTheFloorIsNotHeldFurther(): void
    {
        $startedAt = hrtime(true);
        new ResponseFloor(20)->hold(static function (): void {
            usleep(80_000);
        });

        self::assertLessThan(150_000_000, hrtime(true) - $startedAt, 'The floor is a minimum, never added to the work.');
    }

    public function testAZeroFloorWaitsForNothing(): void
    {
        $startedAt = hrtime(true);
        new ResponseFloor(0)->hold(static fn (): null => null);

        self::assertLessThan(20_000_000, hrtime(true) - $startedAt);
    }

    public function testANegativeFloorIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ResponseFloor(-1);
    }
}
