<?php
declare(strict_types=1);

namespace App\Validation\Gate;

use App\Validation\BatchContext;

/**
 * Gate 4 — intra-batch consistency.
 *
 * A single batch represents one episode at three timepoints. Patient-level
 * fields must not differ between rows carrying the same patient_id. If
 * they do, someone edited the source data between timepoints or the
 * DTS merged two patients by mistake.
 *
 * Therapist and facility fields are checked the same way.
 *
 * Memory-bounded: consults BatchContext for prior rows that share a key
 * rather than scanning the whole batch. The context only buffers rows
 * by patient/therapist/facility id, which is essentially nothing for
 * MDS-PT (3 rows per patient_id max).
 */
final class IntraBatchGate implements ValidationGateInterface
{
    private const PATIENT_STABLE = [
        'Geb_jahr', 'Geb_Monat', 'Sex', 'Gender', 'Familienstand',
        'Diagnose_PD', 'Diagnose_DD', 'Diagnose_BD', 'Diagnose_AD',
        'Diagnose_OCD', 'Diagnose_PTSD', 'Diagnose_SD', 'Diagnose_ED',
        'Diagnose_PBD', 'Diagnose_DMD', 'Diagnose_ADHD', 'Diagnose_ASD',
        'Diagnose_SUD', 'Diagnose_D',
        'CTS_emotionale_misshandlung', 'CTS_koerperliche_misshandlung',
        'CTS_sexueller_missbrauch', 'CTS_emotionale_vernachlaessigung',
        'CTS_koerperliche_vernachlaessigung',
    ];

    private const THERAPIST_STABLE = [
        'facility_id', 'Geburtsjahr_T', 'Geschlecht_T',
        'hat_Approbation_PsychThG', 'Approbation_Jahr',
    ];

    private const FACILITY_STABLE = [
        'plz_einrichtung', 'art_und_setting_einrichtung', 'traeger',
    ];

    public function check(array $row, int $rowIdx, BatchContext $ctx): ?GateFailure
    {
        $pid = $row['patient_id']   ?? '';
        $tid = $row['therapist_id'] ?? '';
        $fid = $row['facility_id']  ?? '';

        if ($pid !== '') {
            $failure = $this->compareAgainstPriors(
                $row, $ctx->priorRowsForPatient($pid), self::PATIENT_STABLE,
            );
            if ($failure !== null) return $failure;
        }

        if ($tid !== '') {
            $failure = $this->compareAgainstPriors(
                $row, $ctx->priorRowsForTherapist($tid), self::THERAPIST_STABLE,
            );
            if ($failure !== null) return $failure;
        }

        if ($fid !== '') {
            $failure = $this->compareAgainstPriors(
                $row, $ctx->priorRowsForFacility($fid), self::FACILITY_STABLE,
            );
            if ($failure !== null) return $failure;
        }

        return null;
    }

    public function number(): int
    {
        return 4;
    }

    /** @param list<array<string,string>> $priors */
    private function compareAgainstPriors(
        array $row,
        array $priors,
        array $fields,
    ): ?GateFailure {
        foreach ($priors as $other) {
            foreach ($fields as $f) {
                $a = trim($row[$f]   ?? '');
                $b = trim($other[$f] ?? '');
                if ($a !== '' && $b !== '' && $a !== $b) {
                    return new GateFailure(
                        4,
                        'INCONSISTENT_ACROSS_ROWS',
                        $f,
                        collidingValue: $a,
                    );
                }
            }
        }
        return null;
    }
}
