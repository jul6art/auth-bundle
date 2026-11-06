<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Realm\Realm;

/**
 * The accounts of a realm, in the base of their application: whichever entity manager maps the realm's class.
 */
final readonly class Accounts
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    public function findByEmail(Realm $realm, string $email): ?ApiAccountInterface
    {
        $account = $this->manager($realm->userClass)->getRepository($realm->userClass)->findOneBy(['email' => $email]);

        return $account instanceof ApiAccountInterface ? $account : null;
    }

    public function save(ApiAccountInterface $account): void
    {
        $manager = $this->manager($account::class);
        $manager->persist($account);
        $manager->flush();
    }

    /**
     * @param class-string $class
     */
    private function manager(string $class): ObjectManager
    {
        return $this->registry->getManagerForClass($class) ?? throw new \LogicException(\sprintf('No entity manager maps "%s".', $class));
    }
}
