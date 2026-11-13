<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Account;

use Jul6Art\AuthBundle\Api\Account\ApiAccountInterface;
use Jul6Art\AuthBundle\Api\Realm\Realm;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The "forgot password" e-mail, in the account's language.
 *
 * - {@see send()}: the API's, from the realm's sender and with its templates — a link to the reset screen of the
 *   account's own application, never one built from the request's host, which a visitor chooses.
 * - {@see deliver()}: any other, described whole — the web screens of admin-bundle send theirs through it.
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

        $this->deliver(new PasswordResetEmail(
            from: new Address($realm->mailFrom, $realm->issuer),
            to: new Address($account->getEmail(), $account->getName()),
            subject: $realm->subject,
            translationDomain: $realm->translationDomain,
            htmlTemplate: $realm->htmlTemplate,
            context: [
                'name' => $account->getName(),
                'link' => $realm->resetLink($locale, $token),
                'app' => $realm->issuer,
                'locale' => $locale,
                'french' => 'fr' === $locale,
            ],
            textTemplate: $realm->textTemplate,
            locale: $locale,
            subjectParameters: ['%app%' => $realm->issuer],
        ));
    }

    public function deliver(PasswordResetEmail $email): void
    {
        $message = new TemplatedEmail()
            ->from($email->from)
            ->to($email->to)
            // ⚠️ Translated HERE: `->subject('key')` would send the key itself as the subject.
            ->subject($this->translator->trans($email->subject, $email->subjectParameters, $email->translationDomain, $email->locale))
            ->htmlTemplate($email->htmlTemplate)
            ->context($email->context);

        if (null !== $email->textTemplate) {
            $message->textTemplate($email->textTemplate);
        }

        // The body renders lazily, in the transport — after the request, maybe in a worker: the language travels
        // with the message.
        if (null !== $email->locale) {
            $message->locale($email->locale);
        }

        $this->mailer->send($message);
    }
}
