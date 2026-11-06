<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Account\LoginRecordInterface;

#[ORM\Entity]
#[ORM\Table(name: 'login_record')]
class LoginRecord implements LoginRecordInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    public function __construct(
        ApiAccountInterface $account,
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $ipAddress,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $userAgent,
    ) {
        if (!$account instanceof Account) {
            throw new \LogicException('A sign-in of realm A is an account of realm A.');
        }

        $this->account = $account;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
