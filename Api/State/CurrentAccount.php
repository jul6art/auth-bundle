<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\State;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The signed-in account of a route that `AUTH_SELF` guards: the voter only lets an {@see ApiAccountInterface}
 * through.
 */
final readonly class CurrentAccount
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function get(): ApiAccountInterface
    {
        $account = $this->security->getUser();
        if (!$account instanceof ApiAccountInterface) {
            throw new \LogicException('The access decision only lets an account of the API sign-in through.');
        }

        return $account;
    }
}
