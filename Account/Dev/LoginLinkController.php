<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account\Dev;

use Symfony\Component\HttpFoundation\Response;

/**
 * The target of the development sign-in link — a controller that is NEVER run.
 *
 * The `login_link` authenticator of the firewall intercepts the request on this route, checks the signature, opens
 * the session and redirects. The controller exists because a route needs one; if its body runs, the firewall does
 * not cover this URL — hence the exception rather than an empty page, which would pretend all is well.
 *
 * Registered in the "dev" environment only, like {@see \Jul6Art\AuthBundle\Account\Command\DevLoginLinkCommand}.
 * The application declares the route under `when@dev` (`dev_login_link_check`).
 */
final class LoginLinkController
{
    public function __invoke(): Response
    {
        throw new \LogicException('This route is intercepted by the login_link authenticator of the firewall. If you read this, the route left the firewall.');
    }
}
