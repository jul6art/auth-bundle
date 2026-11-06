<?php

declare(strict_types=1);

namespace Jul6Art\AuthBundle\Api\ApiResource;

/**
 * Ten backup codes in clear: shown once, stored only as hashes.
 */
final readonly class BackupCodes
{
    /**
     * @param list<string> $backupCodes
     */
    public function __construct(
        public array $backupCodes,
    ) {
    }
}
