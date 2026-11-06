<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Tests\Api\Fixtures\RealmA;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Tests\Api\Fixtures\Shared\AbstractAccount;

#[ORM\Entity]
#[ORM\Table(name: 'account')]
class Account extends AbstractAccount
{
}
