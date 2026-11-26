<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\Oidc;

/**
 * An RSA key of a provider's `jwks`, as the PEM a signature is checked with: its modulus and its exponent, encoded as a
 * SubjectPublicKeyInfo (RFC 5280) — the few bytes of DER that take, rather than a library for them.
 */
final class Jwk
{
    /** rsaEncryption (1.2.840.113549.1.1.1), with its NULL parameters. */
    private const string RSA = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    /**
     * @param string $n the modulus, base64url
     * @param string $e the exponent, base64url
     *
     * @return non-empty-string
     */
    public static function pem(string $n, string $e): string
    {
        $key = self::sequence(self::integer(self::decode($n)).self::integer(self::decode($e)));
        $info = self::sequence(self::RSA."\x03".self::length(\strlen($key) + 1)."\x00".$key);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($info), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function decode(string $base64Url): string
    {
        $bytes = base64_decode(strtr($base64Url, '-_', '+/').str_repeat('=', (4 - \strlen($base64Url) % 4) % 4), true);
        if (false === $bytes || '' === $bytes) {
            throw new OidcRefusal('auth.oidc.invalid', 'a key of the provider that is not base64url');
        }

        return $bytes;
    }

    private static function integer(string $bytes): string
    {
        // An INTEGER is signed: a leading byte with its high bit set gets a zero before it.
        if (\ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".self::length(\strlen($bytes)).$bytes;
    }

    private static function sequence(string $content): string
    {
        return "\x30".self::length(\strlen($content)).$content;
    }

    /**
     * @param int<0, max> $length
     */
    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");

        return \chr((0x80 | \strlen($bytes)) & 0xFF).$bytes;
    }
}
