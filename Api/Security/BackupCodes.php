<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Security;

/**
 * The backup codes of the second factor: ten codes, shown once, stored as SHA-256
 * hashes. The format of admin-bundle's `BackupCodeGenerator`: ten characters without the ones a
 * person misreads (0/O, 1/I), shown as `ABCDE-FGHJK`.
 */
final class BackupCodes
{
    public const int COUNT = 10;

    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return list<string>
     */
    public static function generate(int $count = self::COUNT): array
    {
        $codes = [];
        while (\count($codes) < $count) {
            $code = '';
            for ($i = 0; $i < 10; ++$i) {
                $code .= self::ALPHABET[\random_int(0, \strlen(self::ALPHABET) - 1)];
            }
            $codes[$code] = \substr($code, 0, 5).'-'.\substr($code, 5);
        }

        return \array_values($codes);
    }

    /**
     * Case, dash and spaces do not matter: `abcde fghjk` is the code `ABCDE-FGHJK`.
     */
    public static function normalize(string $code): string
    {
        return (string) \preg_replace('/[^A-Z0-9]/', '', \strtoupper($code));
    }

    public static function hash(string $code): string
    {
        return \hash('sha256', self::normalize($code));
    }
}
