<?php
declare(strict_types=1);

namespace App\Validation\Gate;

/**
 * Gate 3 — numeric ranges.
 *
 * The DB enforces these via DOMAIN types (vas_100, vas_10, plausible_year)
 * and inline CHECKs. Gate 3 catches them earlier so the facility gets a
 * clean error instead of a database constraint violation after partial
 * processing.
 *
 * Configuration deliberately lives in code, not config, because these
 * ranges are part of the validation contract, not a runtime knob.
 */
final class RangeGate implements ValidationGateInterface
{
    /** @var array<string, array{0:int|float, 1:int|float}> */
    private const RANGES = [
        // VAS 0–100
        'vas_PSYBEL' => [0, 100], 'vas_SOZEX100' => [0, 100],
        'vas_SOZDIS100' => [0, 100], 'vas_STIGMA100' => [0, 100],
        'vas_SATIS100' => [0, 100],
        'globales_Wohlbefinden_VAS' => [0, 100],

        // VAS 0–10
        'SSS' => [0, 10],

        // Plausibility
        'Geb_jahr'      => [1900, 2030],
        'Geb_Monat'     => [1, 12],
        'Gewicht'       => [20, 300],
        'Groesse'       => [100, 230],
        'Haushalt_anzahl' => [1, 20],
        'Kinder_Anzahl'   => [0, 20],
        'Approbation_Jahr' => [1950, 2030],

        // CTS — 5-point scale stored as integer
        'CTS_emotionale_misshandlung'        => [0, 4],
        'CTS_koerperliche_misshandlung'      => [0, 4],
        'CTS_sexueller_missbrauch'           => [0, 4],
        'CTS_emotionale_vernachlaessigung'   => [0, 4],
        'CTS_koerperliche_vernachlaessigung' => [0, 4],
    ];

    /** Non-negative integer counts — common case. */
    private const NONNEG = [
        'groesse_einzugsgebiet', 'n_fachpsychotherapeuten', 'n_fachärzte',
        'bettenanzahl', 'behandlungsfaelle_pro_jahr', 'patienten_pro_jahr',
        'Sprechstunden_vor_Behandlung', 'Probatorische_Sitzungen',
        'Einzeltherapie_50min', 'Gruppentherapie_100min',
        'Bezugspersonengespraeche', 'Suizidversuche_anzahl',
    ];

    public function check(array $row, int $rowIdx, \App\Validation\BatchContext $ctx): ?GateFailure
    {
        foreach (self::RANGES as $col => [$min, $max]) {
            if (($row[$col] ?? '') === '') continue;
            if (!is_numeric($row[$col])) {
                return new GateFailure(3, 'NOT_NUMERIC', $col);
            }
            $n = $row[$col] + 0;
            if ($n < $min || $n > $max) {
                return new GateFailure(3, 'OUT_OF_RANGE', $col, (string) $n);
            }
        }

        foreach (self::NONNEG as $col) {
            if (($row[$col] ?? '') === '') continue;
            if (!is_numeric($row[$col]) || $row[$col] + 0 < 0) {
                return new GateFailure(3, 'NEGATIVE_OR_NON_NUMERIC', $col);
            }
        }

        return null;
    }

    public function number(): int
    {
        return 3;
    }
}
