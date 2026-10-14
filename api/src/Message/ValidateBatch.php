<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched by BatchSubmitController after a CSV is safely on disk.
 *
 * Kept deliberately minimal: only the id. All state lives in the DB and
 * on the volume, so the message is replayable on worker restart.
 */
final class ValidateBatch
{
    public function __construct(public readonly string $batchId) {}
}
