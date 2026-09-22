<?php
declare(strict_types=1);

namespace App\Validation\Gate;

/**
 * Gate 1 — schema & completeness.
 *
 * Enforces the "one-shot completed episode" rule:
 *   - all columns the mapping declares as required are present
 *   - treatment end date is filled in
 *   - dates parse
 *
 * Does NOT check value domains (gate 2) or numeric ranges (gate 3).
 */
final class SchemaGate implements ValidationGateInterface
{
    /** Columns that must always carry a value. */
    private const REQUIRED = [
        'facility_id', 'therapist_id', 'patient_id', 'treatment_id',
        'Behandlungsbeginn', 'Behandlungsende_Datum', 'Behandlungsende_Art',
        'Sex', 'Geb_jahr', 'timepoint', 'assessed_at',
        'globales_Wohlbefinden_VAS',
    ];

    private const DATE_COLS = [
        'Behandlungsbeginn', 'Behandlungsende_Datum', 'assessed_at',
    ];

    public function check(array $row, int $rowIdx, \App\Validation\BatchContext $ctx): ?GateFailure
    {
        foreach (self::REQUIRED as $col) {
            if (trim($row[$col] ?? '') === '') {
                return new GateFailure(1, 'REQUIRED_MISSING', $col);
            }
        }

        foreach (self::DATE_COLS as $col) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $row[$col]);
            if ($d === false || $d->format('Y-m-d') !== $row[$col]) {
                return new GateFailure(1, 'DATE_MALFORMED', $col);
            }
        }

        return null;
    }

    public function number(): int
    {
        return 1;
    }
}
