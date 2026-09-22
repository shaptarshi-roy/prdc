<?php
declare(strict_types=1);

namespace App\Validation\Gate;

use App\Validation\BatchContext;

/**
 * A validation gate examines one row and returns either null (pass) or a
 * GateFailure. Gates are run in registration order; a row exits at the
 * first failure.
 *
 * Gates are pure functions of the row except for two:
 *   - IntraBatchGate (gate 4) consults the BatchContext for earlier rows
 *     that share a key, so it doesn't need the whole batch in memory.
 *   - UniquenessGate (gate 5) queries the DB.
 *
 * Most gates ignore the $ctx argument entirely.
 */
interface ValidationGateInterface
{
    /**
     * @param array<string, string> $row     one parsed CSV row
     * @param int                   $rowIdx  1-based row number for error reports
     * @param BatchContext          $ctx     earlier rows of this batch, keyed for lookup
     */
    public function check(array $row, int $rowIdx, BatchContext $ctx): ?GateFailure;

    /** 0..5 — determines gate ordering and is recorded in quarantine. */
    public function number(): int;
}
