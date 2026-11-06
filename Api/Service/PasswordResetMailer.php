<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Service;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "forgot password" e-mail, in the account's language, from the realm's sender and with its templates: a
 * link to the reset screen of the account's own application — never one built from the request's host, which a
 * visitor chooses.
 */
final readonly class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function send(Realm $realm, ApiAccountInterface $account, string $token): void
    {
        $locale = $account->getPreferredLocale();

        $this->mailer->send(
            new TemplatedEmail()
                ->from(new Address($realm->mailFrom, $realm->issuer))
                ->to(new Address($account->getEmail(), $account->getName()))
                ->subject($this->translator->trans($realm->subject, ['%app%' => $realm->issuer], $realm->translationDomain, $locale))
                ->locale($locale)
                ->htmlTemplate($realm->htmlTemplate)
                ->textTemplate($realm->textTemplate)
                ->context([
                    'name' => $account->getName(),
                    'link' => $realm->resetLink($locale, $token),
                    'app' => $realm->issuer,
                    'locale' => $locale,
                    'french' => 'fr' === $locale,
                ]),
        );
    }
}
