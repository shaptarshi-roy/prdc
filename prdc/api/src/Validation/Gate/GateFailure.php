<?php
declare(strict_types=1);

namespace App\Validation\Gate;

/**
 * Immutable record of why a row failed. Kept deliberately minimal —
 * no patient data here, just metadata the facility can use to find the
 * offending row in its own local copy.
 */
final class GateFailure
{
    public function __construct(
        public readonly int     $gateNum,
        public readonly string  $errorCode,
        public readonly ?string $errorField       = null,
        public readonly ?string $collidingValue   = null,
        public readonly ?string $existingBatchId  = null,
    ) {}
}
