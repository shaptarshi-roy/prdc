<?php
declare(strict_types=1);

namespace App\Validation\Gate;

/**
 * Gate 0 — pseudonym format.
 *
 * Rejects batches whose ID columns look like real identifiers. The
 * expected shape is facility-scoped: alphanumeric + hyphen, bounded
 * length, and no character sequences that suggest a name, email, or
 * date of birth.
 */
final class PseudonymFormatGate implements ValidationGateInterface
{
    private const ID_COLUMNS = [
        'facility_id', 'therapist_id', 'patient_id', 'treatment_id',
    ];

    private const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_\-]{1,63}$/';

    public function check(array $row, int $rowIdx, \App\Validation\BatchContext $ctx): ?GateFailure
    {
        foreach (self::ID_COLUMNS as $col) {
            $v = trim($row[$col] ?? '');
            if ($v === '') {
                return new GateFailure(0, 'ID_MISSING', $col);
            }
            if (!preg_match(self::PATTERN, $v)) {
                return new GateFailure(0, 'ID_BAD_FORMAT', $col);
            }
            if (str_contains($v, '@') || preg_match('/\d{4}-\d{2}-\d{2}/', $v)) {
                return new GateFailure(0, 'ID_LOOKS_PERSONAL', $col);
            }
        }
        return null;
    }

    public function number(): int
    {
        return 0;
    }
}
