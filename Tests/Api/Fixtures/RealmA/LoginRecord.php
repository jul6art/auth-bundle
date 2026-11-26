<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Account\AssistedLoginRecordInterface;

#[ORM\Entity]
#[ORM\Table(name: 'login_record')]
/**
 * Realm A keeps who acted for the account in an assisted session; realm B's records do not.
 */
class LoginRecord implements AssistedLoginRecordInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $assistedBy = null;

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

    #[\Override]
    public function assistedBy(string $assistant): void
    {
        $this->assistedBy = $assistant;
    }

    public function getAssistedBy(): ?string
    {
        return $this->assistedBy;
    }
}
