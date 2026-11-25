<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account;

/**
 * The shortest time an answer takes, so that its duration never tells what its content hides.
 *
 * A "forgot password" request for a known address generates a token, writes a row and builds an e-mail; for an unknown
 * one, nothing. Both get the same answer, but not in the same time: measured on cegeta, 1.00 ms against 1.52 ms. Held
 * to a floor, both take the floor. A floor states a property a test checks as is, where a decoy's work "as long as the
 * real one" drifts silently at the first change.
 *
 * - The floor holds when the work throws too: a failure must not answer sooner than a success.
 * - `hrtime()`, a monotonic clock: an NTP adjustment would make `microtime()` go back, hence a negative `usleep()`.
 * - A work longer than the floor is not held further: the floor hides the gap between two short works, not a slow one.
 */
final readonly class ResponseFloor
{
    public function __construct(private int $milliseconds)
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException(\sprintf('A response floor is a duration: %d ms.', $milliseconds));
        }
    }

    /**
     * Runs the work, then waits until the floor has passed since it started.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function hold(callable $work): mixed
    {
        $startedAt = hrtime(true);

        try {
            return $work();
        } finally {
            $elapsed = intdiv(hrtime(true) - $startedAt, 1_000);
            $floor = $this->milliseconds * 1_000;

            if ($elapsed < $floor) {
                // A real wait, measured on the monotonic clock: the floor evens out the time a client sees, which a
                // frozen test clock must not skip.
                usleep($floor - $elapsed);
            }
        }
    }
}
