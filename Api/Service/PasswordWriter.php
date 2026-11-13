<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Service;

use Jul6Art\AuthBundle\Account\PasswordWriter as Moved;

/*
 * Moved to Jul6Art\AuthBundle\Account\PasswordWriter in 2.8 (T20 lot 3): the web screens of admin-bundle call it too, it is
 * no longer the API's alone. The old name stays an alias until 3.0.
 */
class_alias(Moved::class, 'Jul6Art\AuthBundle\Api\Service\PasswordWriter');
