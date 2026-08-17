<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Entity\Interfaces;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as BaseUserInterface;

/**
 * Class UserInterface.
 *
 * PasswordAuthenticatedUserInterface is part of the contract since Symfony 5.3
 * moved getPassword() out of the base UserInterface.
 */
interface UserInterface extends BaseUserInterface, PasswordAuthenticatedUserInterface
{
}
