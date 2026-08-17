<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Factory;

use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;

/**
 * Class UserFactory.
 */
final class UserFactory implements FactoryInterface
{
    /**
     * @throws \InvalidArgumentException if any argument is passed
     */
    #[\Override]
    public static function create(mixed ...$args): User
    {
        if ([] !== $args) {
            throw new \InvalidArgumentException('You don\'t need arguments to create a User');
        }

        return new User();
    }
}
