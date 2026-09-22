<?php
declare(strict_types=1);

namespace App\Validation\Gate;

use Doctrine\DBAL\Connection;

/**
 * Gate 2 — categorical labels.
 *
 * Reads allowed values straight from Postgres:
 *   * ENUM types → pg_enum
 *   * CHECK (x IN (...)) constraints → information_schema
 *
 * This means the schema is the source of truth. When the DBA adds an
 * ENUM value via `ALTER TYPE ... ADD VALUE`, the validator picks it up
 * on next cache miss. No code change, no deploy.
 *
 * Loaded lazily and cached per worker process.
 */
final class CategoricalGate implements ValidationGateInterface
{
    /** @var array<string, list<string>>|null  column_name → allowed values */
    private ?array $allowed = null;

    /** Multi-select columns (the CSV stores ';' or ',' separated). */
    private const MULTI_COLUMNS = [
        'kostenuebername', 'behandelte_altersgruppe', 'angebotene_pt_verfahren',
        'angebot_stoerungsspezifisch', 'pt_setting', 'Gender', 'Familienstand',
        'Fachkunde_Schwerpunkt', 'Fachkunde_Richtlinienverfahren',
        'therapeutische_Orientierung', 'Therapie_Setting', 'Therapie_Format',
    ];

    public function __construct(private readonly Connection $db) {}

    public function check(array $row, int $rowIdx, \App\Validation\BatchContext $ctx): ?GateFailure
    {
        if ($this->allowed === null) {
            $this->allowed = $this->loadAllowedValues();
        }

        foreach ($this->allowed as $col => $values) {
            $raw = trim($row[$col] ?? '');
            if ($raw === '') {
                continue;                         // nullability is gate 1's job
            }

            $submitted = in_array($col, self::MULTI_COLUMNS, true)
                ? preg_split('/\s*[;,]\s*/', $raw) ?: []
                : [$raw];

            foreach ($submitted as $v) {
                if (!in_array($v, $values, true)) {
                    return new GateFailure(
                        2,
                        'UNKNOWN_LABEL',
                        $col,
                        collidingValue: $v,
                    );
                }
            }
        }

        return null;
    }

    public function number(): int
    {
        return 2;
    }

    /** @return array<string, list<string>> */
    private function loadAllowedValues(): array
    {
        $out = [];

        // 1) Columns backed by an ENUM type.
        $sql = <<<SQL
            SELECT c.column_name, e.enumlabel
            FROM   information_schema.columns c
            JOIN   pg_type t   ON t.typname = c.udt_name
            JOIN   pg_enum e   ON e.enumtypid = t.oid
            WHERE  c.table_schema = 'public'
              AND  c.udt_name LIKE '%_t'
            ORDER  BY c.column_name, e.enumsortorder
        SQL;
        foreach ($this->db->executeQuery($sql)->fetchAllAssociative() as $r) {
            $out[$r['column_name']][] = $r['enumlabel'];
        }

        // 2) Columns bound by a CHECK (col IN ('a','b',...)) constraint.
        //    pg_get_constraintdef is the cleanest way to recover the value
        //    list since Postgres doesn't expose it as structured data.
        $sql = <<<SQL
            SELECT a.attname AS column_name, pg_get_constraintdef(c.oid) AS def
            FROM   pg_constraint c
            JOIN   pg_class     r ON r.oid = c.conrelid
            JOIN   pg_namespace n ON n.oid = r.relnamespace
            JOIN   pg_attribute a ON a.attrelid = r.oid AND a.attnum = ANY(c.conkey)
            WHERE  n.nspname = 'public'
              AND  c.contype = 'c'
        SQL;
        foreach ($this->db->executeQuery($sql)->fetchAllAssociative() as $r) {
            $def = $r['def'];
            if (!preg_match('/\bIN\s*\((.*)\)/is', $def, $m)) {
                continue;
            }
            // Extract quoted literals.
            if (preg_match_all("/'((?:[^']|'')*)'/", $m[1], $lits)) {
                foreach ($lits[1] as $lit) {
                    $out[$r['column_name']][] = str_replace("''", "'", $lit);
                }
            }
        }

        return $out;
    }
}
