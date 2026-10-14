#!/usr/bin/env python3
"""
Generate PHP insert methods for App\\Validation\\ValidationPipeline.

Reads mapping.json and emits one `insert<Table>` method per data table.
The output is a single PHP file (PipelineInserts.php) containing a trait
that ValidationPipeline uses via `use PipelineInserts;`.

This keeps schema and writer in lockstep: every time you regenerate
schema.sql, regenerate this file too, and the DBAL inserts match.

Usage:
    python3 generate_pipeline_inserts.py \\
        --mapping mapping.json \\
        --out     ../api/src/Validation/PipelineInserts.php
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path


# --------------------------------------------------------------------------- #
# Table order maps directly to ValidationPipeline's write sequence.           #
# --------------------------------------------------------------------------- #

TABLE_METHODS = {
    "facilities":      ("insertFacility",      True),
    "therapists":      ("insertTherapist",     True),
    "patients_adult":  ("insertPatient",       True),
    "treatments":      ("insertTreatment",     True),
    "assessments":     ("insertAssessment",    False),   # inserted every row
    "process_ratings": ("insertProcessRating", False),
}

# FKs that the mapping doesn't list as columns but the schema injects.
# Match the auto-FK logic in generate_schema.py.
AUTO_FK_COLUMNS = {
    "therapists":     ["facility_id"],
    "treatments":     ["patient_id", "therapist_id", "facility_id"],
    "assessments":    ["treatment_id"],
    "process_ratings":["treatment_id"],
}

# Columns that ValidationPipeline sets itself (not read from the CSV row).
EXTRA_COLUMNS = {
    "facilities":      [("first_seen_batch", "uuid")],
    "therapists":      [("first_seen_batch", "uuid")],
    "patients_adult":  [("first_seen_batch", "uuid")],
    "treatments":      [("batch_id",         "uuid")],
    "assessments":     [("batch_id",         "uuid")],
    "process_ratings": [("batch_id",         "uuid")],
}


# --------------------------------------------------------------------------- #
# Per-column PHP value-expression and DBAL param-type                         #
# --------------------------------------------------------------------------- #

def php_expr(col: str, spec: dict) -> str:
    """PHP expression producing the insert value from $row[$col]."""
    type_hint = spec.get("type_hint")
    is_multi  = spec.get("is_multi", False)
    has_enum  = (
        spec.get("codebook_ref") is not None
        and not spec.get("type_hint")
    )

    esc = f"$row[{php_str(col)}]"

    # Arrays — split and filter.
    if is_multi:
        return f"$this->toArray({esc} ?? '')"

    # Booleans — coerce. MDS CSVs use 1/0 or empty.
    if type_hint == "bool":
        return f"$this->boolOrNull({esc} ?? '')"

    # Integers / smallints.
    if type_hint in ("smallint", "integer", "plz_de", "plausible_year"):
        return f"$this->intOrNull({esc} ?? '')"

    # VAS / numeric scales.
    if type_hint in ("vas_100", "vas_10"):
        return f"$this->intOrNull({esc} ?? '')"

    # numeric(X,Y).
    if type_hint and type_hint.startswith("numeric"):
        return f"$this->floatOrNull({esc} ?? '')"

    # Dates.
    if type_hint == "date":
        return f"$this->dateOrNull({esc} ?? '')"

    # ISO codes or text.
    if type_hint in ("iso3166_3", "iso639_3", "text"):
        return f"$this->strOrNull({esc} ?? '')"

    # ENUM- or CHECK-backed categorical scalar — string, but pass '' as null.
    if has_enum or type_hint is None:
        return f"$this->strOrNull({esc} ?? '')"

    # Fallback.
    return f"{esc} ?? null"


def dbal_param_type(col: str, spec: dict) -> str | None:
    """Returns a PHP literal for DBAL's $types map, or None if not needed."""
    if spec.get("is_multi", False):
        return "Connection::PARAM_STR_ARRAY"
    if spec.get("type_hint") == "bool":
        return "'boolean'"
    return None


def php_str(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


# --------------------------------------------------------------------------- #
# Render a single insert method                                               #
# --------------------------------------------------------------------------- #

def render_method(
    method_name: str,
    table: str,
    columns: list[tuple[str, dict]],
    is_upsert_guarded: bool,
) -> str:
    """Render one insertFoo() method as PHP source."""

    lines = [
        f"    /** Insert one row into {table}. */",
        f"    private function {method_name}(array $row, Uuid $batchId): void",
        "    {",
        "        $data = [",
    ]

    # Auto FK columns come first — they're always required.
    for fk in AUTO_FK_COLUMNS.get(table, []):
        lines.append(f"            {php_str(fk)} => $row[{php_str(fk)}],")

    # Mapped CSV columns.
    for col, spec in columns:
        lines.append(f"            {php_str(col)} => {php_expr(col, spec)},")

    # Extra (batch_id / first_seen_batch).
    for col, _ in EXTRA_COLUMNS.get(table, []):
        lines.append(f"            {php_str(col)} => $batchId,")

    lines.append("        ];")

    # Build $types for array + uuid columns.
    lines.append("")
    lines.append("        $types = [")
    for col, spec in columns:
        t = dbal_param_type(col, spec)
        if t:
            lines.append(f"            {php_str(col)} => {t},")
    for col, t in EXTRA_COLUMNS.get(table, []):
        lines.append(f"            {php_str(col)} => 'uuid',")
    lines.append("        ];")

    lines.append("")
    lines.append(f"        $this->db->insert('{table}', $data, $types);")
    lines.append("    }")
    return "\n".join(lines)


# --------------------------------------------------------------------------- #
# Shared helpers (small utility methods the trait adds to the pipeline)       #
# --------------------------------------------------------------------------- #

HELPERS = r"""
    // -- Value coercion helpers -----------------------------------------

    /** Split a cell like "a; b; c" into a trimmed, non-empty array. */
    private function toArray(string $cell): array
    {
        $cell = trim($cell);
        if ($cell === '') return [];
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*[;,]\s*/', $cell),
        )));
    }

    private function intOrNull(string $v): ?int
    {
        $v = trim($v);
        return $v === '' ? null : (int) $v;
    }

    private function floatOrNull(string $v): ?float
    {
        $v = trim(str_replace(',', '.', $v));
        return $v === '' ? null : (float) $v;
    }

    private function strOrNull(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    /** CSV booleans: '1'/'true'/'ja' = true, '0'/'false'/'nein' = false. */
    private function boolOrNull(string $v): ?bool
    {
        $v = strtolower(trim($v));
        if ($v === '') return null;
        return in_array($v, ['1', 'true', 't', 'ja', 'yes'], true);
    }

    private function dateOrNull(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return null;
        // Keep as string — DBAL's default date handling expects 'Y-m-d'.
        return $v;
    }
"""


# --------------------------------------------------------------------------- #
# File skeleton                                                               #
# --------------------------------------------------------------------------- #

HEADER = """\
<?php
declare(strict_types=1);

namespace App\\Validation;

use Doctrine\\DBAL\\Connection;
use Symfony\\Component\\Uid\\Uuid;

/**
 * Trait emitted by tools/generate_pipeline_inserts.py.
 *
 * Keeps per-table DBAL insert statements aligned with mapping.json and
 * db/init/01-schema.sql. DO NOT edit by hand — rerun the generator.
 *
 * This trait is used by {@see ValidationPipeline}; it relies on that class
 * providing a `$db` (Connection) property.
 */
trait PipelineInserts
{
"""

FOOTER = """\
}
"""


# --------------------------------------------------------------------------- #
# Main                                                                        #
# --------------------------------------------------------------------------- #

def group_by_table(mapping: dict) -> dict[str, list[tuple[str, dict]]]:
    tables: dict[str, list[tuple[str, dict]]] = {}
    for key, spec in mapping["columns"].items():
        if key.startswith("_"):
            continue
        sql_name = spec.get("sql_name", key)
        tables.setdefault(spec["table"], []).append((sql_name, spec))
    return tables


def generate(mapping_path: Path) -> str:
    mapping = json.loads(mapping_path.read_text(encoding="utf-8"))
    grouped = group_by_table(mapping)

    out: list[str] = [HEADER]

    for table, (method_name, _guard) in TABLE_METHODS.items():
        cols = grouped.get(table, [])
        out.append(render_method(method_name, table, cols, False))
        out.append("")

    out.append(HELPERS)
    out.append(FOOTER)

    return "\n".join(out)


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--mapping", required=True, type=Path)
    ap.add_argument("--out",     required=True, type=Path)
    args = ap.parse_args()

    src = generate(args.mapping)
    args.out.write_text(src, encoding="utf-8")
    sys.stderr.write(f"wrote {args.out} ({len(src):,} bytes)\n")
    return 0


if __name__ == "__main__":
    sys.exit(main())
