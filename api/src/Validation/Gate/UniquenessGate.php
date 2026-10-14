<?php
declare(strict_types=1);

namespace App\Validation\Gate;

use Doctrine\DBAL\Connection;

/**
 * Gate 5 — uniqueness guard.
 *
 * The only gate that queries the live database. Because a submission
 * represents a completed episode and facilities generate fresh
 * pseudonyms per episode, seeing an existing patient_id or treatment_id
 * means something has gone wrong:
 *
 *   - PATIENT_ID_EXISTS  / TREATMENT_ID_EXISTS — duplicate submit, or
 *     a pseudonym generator collision. Auto-reject, needs human review.
 *
 *   - THERAPIST_INCONSISTENT / FACILITY_INCONSISTENT — therapist or
 *     facility known from prior batches but carrying different values
 *     this time. Same failure class, different error code.
 *
 * Failures here always route to quarantine with the collision's
 * original batch_id attached so reviewers can compare.
 */
final class UniquenessGate implements ValidationGateInterface
{
    public function __construct(private readonly Connection $db) {}

    public function check(array $row, int $rowIdx, \App\Validation\BatchContext $ctx): ?GateFailure
    {
        // 5a) patient_id must never have been seen before.
        $existing = $this->db->fetchOne(
            'SELECT first_seen_batch FROM patients_adult WHERE patient_id = ?',
            [$row['patient_id']],
        );
        if ($existing !== false) {
            return new GateFailure(
                5,
                'PATIENT_ID_EXISTS',
                'patient_id',
                collidingValue: $row['patient_id'],
                existingBatchId: (string) $existing,
            );
        }

        // 5b) treatment_id must never have been seen before.
        $existing = $this->db->fetchOne(
            'SELECT batch_id FROM treatments WHERE treatment_id = ?',
            [$row['treatment_id']],
        );
        if ($existing !== false) {
            return new GateFailure(
                5,
                'TREATMENT_ID_EXISTS',
                'treatment_id',
                collidingValue: $row['treatment_id'],
                existingBatchId: (string) $existing,
            );
        }

        // 5c) therapist may recur but must be consistent.
        $therapist = $this->db->fetchAssociative(
            'SELECT "facility_id", "Geburtsjahr_T", "Approbation_Jahr"
               FROM therapists WHERE therapist_id = ?',
            [$row['therapist_id']],
        );
        if ($therapist !== false) {
            foreach ($therapist as $field => $storedValue) {
                $submitted = (string) ($row[$field] ?? '');
                if ($submitted !== '' && (string) $storedValue !== $submitted) {
                    return new GateFailure(
                        5,
                        'THERAPIST_INCONSISTENT',
                        $field,
                        collidingValue: $submitted,
                    );
                }
            }
        }

        // 5d) facility may recur but must be consistent (PLZ, setting, traeger).
        $facility = $this->db->fetchAssociative(
            'SELECT plz_einrichtung, art_und_setting_einrichtung, traeger
               FROM facilities WHERE facility_id = ?',
            [$row['facility_id']],
        );
        if ($facility !== false) {
            foreach ($facility as $field => $storedValue) {
                $submitted = (string) ($row[$field] ?? '');
                if ($submitted !== '' && (string) $storedValue !== $submitted) {
                    return new GateFailure(
                        5,
                        'FACILITY_INCONSISTENT',
                        $field,
                        collidingValue: $submitted,
                    );
                }
            }
        }

        return null;
    }

    public function number(): int
    {
        return 5;
    }
}
