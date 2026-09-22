#!/usr/bin/env python3
"""
Generate schema.sql from the MDS codebooks and a column mapping.

Usage:
    python generate_schema.py \\
        --adult   MDS_Adult_codebook.json \\
        --pt      MDS_PT_codebook.json \\
        --mapping mapping.json \\
        --out     schema.sql

The mapping file declares, for each CSV/SQL column, which table it belongs to,
which codebook entry defines its value domain, and any overrides (type hint,
not_null, multi-select, numeric range, etc.).

Column types are derived as follows:

    codebook entry is a list of strings AND is_multi = false
        → dedicated ENUM type <column>_t, column is that ENUM

    codebook entry is a list AND is_multi = true
        → dedicated ENUM type <column>_t, column is <column>_t[]

    codebook entry is absent or not a list
        → column uses type_hint

Regenerate this schema any time a codebook changes. The output is
deterministic: the same inputs produce byte-identical output.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any


# --------------------------------------------------------------------------- #
# Helpers                                                                     #
# --------------------------------------------------------------------------- #

def walk_pt(obj: Any, path: str = "") -> dict[str, dict]:
    """Flatten the PT codebook into {path: leaf_dict}."""
    out: dict[str, dict] = {}
    if isinstance(obj, dict):
        if "Antwortoptionen" in obj or "Itemformulierung" in obj:
            out[path] = obj
        else:
            for k, v in obj.items():
                out.update(walk_pt(v, f"{path}/{k}"))
    return out


def resolve_ref(ref: str, adult: dict, pt_flat: dict) -> dict | None:
    """Resolve 'adult:Foo' or 'pt:/a/b/c' to the codebook leaf dict."""
    if ref.startswith("adult:"):
        return adult.get(ref[len("adult:"):])
    if ref.startswith("pt:"):
        return pt_flat.get(ref[len("pt:"):])
    raise ValueError(f"unknown ref scheme: {ref}")


def sql_quote_ident(name: str) -> str:
    """Always quote identifiers — names contain German chars, mixed case."""
    return '"' + name.replace('"', '""') + '"'


def sql_quote_literal(val: str) -> str:
    return "'" + val.replace("'", "''") + "'"


# --------------------------------------------------------------------------- #
# Data model                                                                  #
# --------------------------------------------------------------------------- #

@dataclass
class Column:
    name: str                          # SQL column name (from mapping key)
    table: str
    sql_type: str                      # rendered SQL, e.g. 'sex_t[]'
    not_null: bool = False
    checks: list[str] = field(default_factory=list)
    enum_type_name: str | None = None  # if a dedicated ENUM was created
    enum_values: list[str] | None = None
    comment: str | None = None


@dataclass
class Schema:
    columns: list[Column] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    enum_types_by_column: dict[str, str] = field(default_factory=dict)
    enum_by_values: dict[tuple, str] = field(default_factory=dict)


# --------------------------------------------------------------------------- #
# Column resolution                                                           #
# --------------------------------------------------------------------------- #

def resolve_column(
    col_name: str,
    spec: dict,
    adult: dict,
    pt_flat: dict,
    schema: Schema,
) -> Column:
    table = spec["table"]
    not_null = bool(spec.get("not_null", False))
    ref = spec.get("codebook_ref")
    type_hint = spec.get("type_hint")
    is_multi = spec.get("is_multi", False)
    rng = spec.get("range")
    nonneg = spec.get("nonnegative", False)

    col = Column(name=col_name, table=table, sql_type="", not_null=not_null)
    col.comment = spec.get("notes")

    # Resolve codebook-defined enumerations first.
    cb_leaf = None
    if ref is not None:
        cb_leaf = resolve_ref(ref, adult, pt_flat)
        if cb_leaf is None:
            schema.warnings.append(
                f"{col_name}: codebook_ref {ref!r} not found in codebooks"
            )

    # If the codebook gives a finite list of labels, use it.
    if cb_leaf is not None:
        ao = cb_leaf.get("Antwortoptionen")
        if isinstance(ao, list) and all(isinstance(x, str) for x in ao):
            # Skip if the single "option" is actually a VAS hint like
            # ['Visuelle Analogskala'] — treat as numeric via type_hint.
            if len(ao) == 1 and (
                "Visuelle Analogskala" in ao[0]
                or "Numerisches Feld" in ao[0]
            ):
                if not type_hint:
                    type_hint = "vas_100"
            else:
                # Some codebook items contain blank strings as "anchor"
                # positions on a 7-point scale. Postgres ENUMs can't hold
                # duplicate labels (including empty). Fall back to
                # TEXT + CHECK, preserving the original value list with
                # blanks collapsed to numeric positions.
                has_blanks = any(v.strip() == "" for v in ao)
                too_long = any(len(v.encode("utf-8")) > 63 for v in ao)

                if has_blanks:
                    # Replace blanks with their numeric position (1..N) so
                    # a CSV can submit either the labeled endpoints or a
                    # number for the unlabeled middle points.
                    values = []
                    for i, v in enumerate(ao, start=1):
                        values.append(v if v.strip() else str(i))
                    col.sql_type = "TEXT" + ("[]" if is_multi else "")
                    q = sql_quote_ident(col_name)
                    values_sql = ", ".join(sql_quote_literal(v) for v in values)
                    if is_multi:
                        col.checks.append(f"{q} <@ ARRAY[{values_sql}]::text[]")
                    else:
                        col.checks.append(f"{q} IN ({values_sql})")
                    col.enum_values = values
                elif too_long:
                    col.sql_type = "TEXT" + ("[]" if is_multi else "")
                    q = sql_quote_ident(col_name)
                    values_sql = ", ".join(sql_quote_literal(v) for v in ao)
                    if is_multi:
                        col.checks.append(f"{q} <@ ARRAY[{values_sql}]::text[]")
                    else:
                        col.checks.append(f"{q} IN ({values_sql})")
                    col.enum_values = list(ao)
                else:
                    # Dedupe: if a prior column emitted an ENUM with the
                    # same value list, reuse it instead of creating a twin.
                    key = tuple(ao)
                    existing = schema.enum_by_values.get(key)
                    if existing:
                        col.sql_type = existing + ("[]" if is_multi else "")
                    else:
                        enum_name = col_name.lower() + "_t"
                        enum_name = re.sub(r"[^a-z0-9_]", "_", enum_name)
                        col.enum_type_name = enum_name
                        col.enum_values = list(ao)
                        col.sql_type = enum_name + ("[]" if is_multi else "")
                        schema.enum_types_by_column[col_name] = enum_name
                        schema.enum_by_values[key] = enum_name
                # Add a NOT EMPTY check to prevent empty arrays.
                if is_multi and not_null:
                    col.checks.append(
                        f"array_length({sql_quote_ident(col_name)}, 1) >= 1"
                    )

    # enum_ref: use an ENUM defined for a different column.
    if not col.sql_type:
        ref_col = spec.get("enum_ref")
        if ref_col:
            ref_spec = schema.enum_types_by_column.get(ref_col)
            if ref_spec is None:
                schema.warnings.append(
                    f"{col_name}: enum_ref {ref_col!r} not yet resolved"
                )
            else:
                col.sql_type = ref_spec + ("[]" if is_multi else "")

    # Otherwise honor the type_hint.
    if not col.sql_type:
        if not type_hint:
            schema.warnings.append(
                f"{col_name}: no ENUM resolved and no type_hint — defaulting to TEXT"
            )
            type_hint = "text"

        mapping = {
            "bool":            "BOOLEAN",
            "smallint":        "SMALLINT",
            "integer":         "INTEGER",
            "text":            "TEXT",
            "date":            "DATE",
            "vas_100":         "vas_100",
            "vas_10":          "vas_10",
            "plz_de":          "plz_de",
            "plausible_year":  "plausible_year",
            "iso3166_3":       "CHAR(3)",
            "iso639_3":        "CHAR(3)",
            "uuid":            "UUID",
            "timepoint_t":     "timepoint_t",
        }
        if type_hint.startswith("numeric"):
            col.sql_type = type_hint.upper()
        elif type_hint in mapping:
            col.sql_type = mapping[type_hint]
        else:
            schema.warnings.append(
                f"{col_name}: unknown type_hint {type_hint!r} — using TEXT"
            )
            col.sql_type = "TEXT"

    # Range / nonneg constraints.
    q = sql_quote_ident(col_name)
    if rng and col.sql_type.upper() not in ("BOOLEAN", "DATE"):
        col.checks.append(f"{q} BETWEEN {rng[0]} AND {rng[1]}")
    elif nonneg:
        col.checks.append(f"{q} >= 0")

    return col


# --------------------------------------------------------------------------- #
# SQL emitter                                                                 #
# --------------------------------------------------------------------------- #

HEADER = """\
-- =====================================================================
--  MDS-PT database schema for P-RDC
--  GENERATED from MDS_Adult_codebook.json + MDS_PT_codebook.json
--  + mapping.json. Do not edit by hand — rerun generate_schema.py.
-- =====================================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Reusable domain types --------------------------------------------------
CREATE DOMAIN vas_100 AS SMALLINT CHECK (VALUE BETWEEN 0 AND 100);
CREATE DOMAIN vas_10  AS SMALLINT CHECK (VALUE BETWEEN 0 AND 10);
CREATE DOMAIN plz_de  AS INTEGER  CHECK (VALUE BETWEEN 0 AND 99999);
CREATE DOMAIN plausible_year AS SMALLINT
    CHECK (VALUE BETWEEN 1900 AND EXTRACT(YEAR FROM CURRENT_DATE)::SMALLINT);

-- Fixed ENUM — not derived from a specific column's Antwortoptionen
CREATE TYPE timepoint_t AS ENUM ('pre', 'mid', 'post', 'follow-up');

"""


BATCH_LOG_DDL = """\
-- =====================================================================
--  BATCH PROVENANCE — referenced by every data table via batch_id FK
-- =====================================================================

CREATE TABLE batch_log (
    batch_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    facility_id     TEXT NOT NULL,
    received_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    validated_at    TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    raw_checksum    CHAR(64) NOT NULL,
    row_count       INTEGER,
    accepted_count  INTEGER,
    rejected_count  INTEGER,
    -- Incremented every time the worker starts processing this batch.
    -- A batch where the worker crashed will have attempts > 1 on its
    -- next run; if attempts exceeds MAX_ATTEMPTS the handler gives up
    -- and marks it 'failed' so it stops redelivering.
    attempts        SMALLINT NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'received'
                    CHECK (status IN ('received', 'validating',
                                      'completed', 'partial_quarantine',
                                      'blocked_for_review', 'failed'))
);

CREATE INDEX batch_log_facility_idx ON batch_log (facility_id, received_at DESC);
CREATE INDEX batch_log_status_idx   ON batch_log (status) WHERE status != 'completed';
"""


FOOTER = """\

-- =====================================================================
--  QUARANTINE — metadata only, no patient payload
-- =====================================================================

CREATE TABLE quarantine (
    quarantine_id     BIGSERIAL PRIMARY KEY,
    batch_id          UUID NOT NULL REFERENCES batch_log(batch_id),
    row_idx           INTEGER NOT NULL,
    gate_num          SMALLINT NOT NULL CHECK (gate_num BETWEEN 0 AND 5),
    error_code        TEXT NOT NULL,
    error_field       TEXT,
    colliding_value   TEXT,
    existing_batch_id UUID REFERENCES batch_log(batch_id),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX quarantine_batch_idx ON quarantine (batch_id);

-- =====================================================================
--  ROLES — idempotent (safe to re-run)
--
--  Three roles, each least-privilege for its container:
--
--    api_role     — used by the api container (HTTP layer).
--                   Can write batch_log on submission and the Messenger
--                   transport. Has NO access to data tables — even RCE
--                   in the api container cannot inject patient data.
--
--    worker_role  — used by the worker container.
--                   Writes the six data tables, updates batch_log status,
--                   reads what gate 5 needs. Cannot accept new batches.
--
--    researcher   — read-only on the projection view.
-- =====================================================================

DO $$ BEGIN
  CREATE ROLE researcher NOINHERIT;
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  CREATE ROLE api_role NOINHERIT;
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
  CREATE ROLE worker_role NOINHERIT;
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

GRANT USAGE ON SCHEMA public TO researcher, api_role, worker_role;

-- ------- api_role ----------------------------------------------------
-- The api container creates new batch_log rows on submission and
-- enqueues Messenger jobs. It also reads batch_log + quarantine for the
-- status / errors endpoints (limited to the caller's own facility,
-- enforced in application code). It has NO access to data tables.
GRANT INSERT ON batch_log               TO api_role;
GRANT SELECT ON batch_log, quarantine   TO api_role;

-- The Doctrine Messenger transport table is created at runtime by
-- Symfony Messenger; grant defaults so it's covered automatically.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO api_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO api_role;

-- ------- worker_role -------------------------------------------------
-- The worker writes the six data tables, the quarantine, and updates
-- batch_log status fields. It also needs SELECT on data tables so gate 5
-- can detect collisions. It does NOT get INSERT on batch_log itself —
-- only the api creates batches; the worker only updates their status.
GRANT INSERT ON facilities, therapists, patients_adult,
                 treatments, assessments, process_ratings,
                 quarantine                                TO worker_role;
-- DELETE on quarantine only — needed to clean up stale entries on retry.
GRANT DELETE ON quarantine                                TO worker_role;
GRANT UPDATE (validated_at, completed_at, status,
              row_count, accepted_count, rejected_count,
              attempts)
      ON batch_log                                          TO worker_role;
GRANT SELECT ON facilities, therapists, patients_adult,
                 treatments, batch_log, quarantine          TO worker_role;
GRANT USAGE  ON SEQUENCE quarantine_quarantine_id_seq       TO worker_role;

-- The worker also consumes from the Messenger transport table.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO worker_role;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO worker_role;

COMMIT;
"""


# The PK / FK / unique-constraint layout that the mapping doesn't specify.
# Handwritten because these aren't codebook-derivable.
TABLE_SHAPE = {
    "facilities": {
        "pk": "facility_id",
        "fks": [],
        "extra_columns": [
            # provenance columns appended to every table
            ('first_seen_at',   'TIMESTAMPTZ NOT NULL DEFAULT now()'),
            ('first_seen_batch', 'UUID REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [],
    },
    "therapists": {
        "pk": "therapist_id",
        "fks": [("facility_id", "facilities(facility_id)")],
        "extra_columns": [
            ('first_seen_at',   'TIMESTAMPTZ NOT NULL DEFAULT now()'),
            ('first_seen_batch', 'UUID REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [
            # coherence: if therapist claims an Approbation, the year must
            # be present (and vice versa).
            '(("hat_Approbation_PsychThG" = false AND "Approbation_Jahr" IS NULL)'
            '  OR ("hat_Approbation_PsychThG" = true AND "Approbation_Jahr" IS NOT NULL))',
        ],
    },
    "patients_adult": {
        "pk": "patient_id",
        "fks": [],
        "extra_columns": [
            ('first_seen_at',   'TIMESTAMPTZ NOT NULL DEFAULT now()'),
            ('first_seen_batch', 'UUID REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [
            # Kinder flag consistent with Kinder_Anzahl
            '(("Kinder" = false AND COALESCE("Kinder_Anzahl", 0) = 0)'
            '  OR ("Kinder" = true AND "Kinder_Anzahl" IS NOT NULL AND "Kinder_Anzahl" > 0)'
            '  OR ("Kinder" IS NULL))',
        ],
    },
    "treatments": {
        "pk": "treatment_id",
        "fks": [
            ("patient_id",   "patients_adult(patient_id)", "UNIQUE"),
            ("therapist_id", "therapists(therapist_id)"),
            ("facility_id",  "facilities(facility_id)"),
        ],
        "extra_columns": [
            ('batch_id', 'UUID NOT NULL REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [
            '"Behandlungsende_Datum" >= "Behandlungsbeginn"',
        ],
    },
    "assessments": {
        "pk": None,  # synthetic UUID below
        "fks": [("treatment_id", "treatments(treatment_id)")],
        "extra_columns": [
            ('assessment_id', 'UUID PRIMARY KEY DEFAULT gen_random_uuid()'),
            ('batch_id',      'UUID NOT NULL REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [],
        "uniques": [("treatment_id", "timepoint")],
        "prepend_columns_first": True,  # put assessment_id first
    },
    "process_ratings": {
        "pk": None,
        "fks": [("treatment_id", "treatments(treatment_id)")],
        "extra_columns": [
            ('process_id', 'UUID PRIMARY KEY DEFAULT gen_random_uuid()'),
            ('batch_id',   'UUID NOT NULL REFERENCES batch_log(batch_id)'),
        ],
        "extra_checks": [
            "timepoint != 'pre'",
        ],
        "uniques": [("treatment_id", "timepoint")],
        "prepend_columns_first": True,
    },
}


def emit_enum(col: Column) -> str:
    lines = [f"CREATE TYPE {col.enum_type_name} AS ENUM ("]
    for i, v in enumerate(col.enum_values):
        comma = "," if i < len(col.enum_values) - 1 else ""
        lines.append(f"    {sql_quote_literal(v)}{comma}")
    lines.append(");")
    return "\n".join(lines)


def emit_table(table_name: str, cols: list[Column]) -> str:
    shape = TABLE_SHAPE[table_name]
    lines = [f"CREATE TABLE {table_name} ("]

    col_defs = []
    declared_names = {c.name for c in cols}

    # Synthetic-PK tables get their extra columns up front.
    if shape.get("prepend_columns_first"):
        for name, ddl in shape["extra_columns"]:
            col_defs.append(f"    {sql_quote_ident(name)}  {ddl}")

    # Auto-inject any FK column the mapping didn't declare. These are
    # pseudonym identifiers pointing at another table — always TEXT NOT NULL
    # REFERENCES <target>.
    for fk in shape.get("fks", []):
        fk_col, fk_ref = fk[0], fk[1]
        if fk_col not in declared_names:
            extra = " UNIQUE" if len(fk) > 2 and fk[2] == "UNIQUE" else ""
            col_defs.append(
                f"    {sql_quote_ident(fk_col)} TEXT NOT NULL "
                f"REFERENCES {fk_ref}{extra}"
            )

    for col in cols:
        parts = [sql_quote_ident(col.name), " ", col.sql_type]
        # PK
        if shape.get("pk") == col.name:
            parts.append(" PRIMARY KEY")
        # FK
        for fk in shape.get("fks", []):
            fk_col, fk_ref = fk[0], fk[1]
            if fk_col == col.name:
                parts.append(f" NOT NULL REFERENCES {fk_ref}")
                if len(fk) > 2 and fk[2] == "UNIQUE":
                    parts.append(" UNIQUE")
                break
        if col.not_null and "PRIMARY KEY" not in "".join(parts) \
                and "REFERENCES" not in "".join(parts):
            parts.append(" NOT NULL")
        # CHECK constraints inline
        for chk in col.checks:
            parts.append(f" CHECK ({chk})")
        col_defs.append("    " + "".join(parts))

    # Synthetic-PK tables already had extras; others append here.
    if not shape.get("prepend_columns_first"):
        for name, ddl in shape["extra_columns"]:
            col_defs.append(f"    {sql_quote_ident(name)}  {ddl}")

    # Extra table-level checks.
    for chk in shape.get("extra_checks", []):
        col_defs.append(f"    CHECK ({chk})")

    # Uniques.
    for u in shape.get("uniques", []):
        cols_str = ", ".join(sql_quote_ident(c) for c in u)
        col_defs.append(f"    UNIQUE ({cols_str})")

    lines.append(",\n".join(col_defs))
    lines.append(");")

    # Indexes on all FK columns.
    for fk in shape.get("fks", []):
        fk_col = fk[0]
        idx_name = f"{table_name}_{fk_col.lower()}_idx"
        lines.append(
            f"CREATE INDEX {idx_name} ON {table_name} ({sql_quote_ident(fk_col)});"
        )

    return "\n".join(lines)


def generate(adult_path: Path, pt_path: Path, mapping_path: Path) -> str:
    with open(adult_path, encoding="utf-8") as fh:
        adult = json.load(fh)
    with open(pt_path, encoding="utf-8") as fh:
        pt = json.load(fh)
    with open(mapping_path, encoding="utf-8") as fh:
        mapping = json.load(fh)

    pt_flat = walk_pt(pt.get("modules", pt))
    schema = Schema()

    # Resolve every column.
    for col_name, spec in mapping["columns"].items():
        sql_name = spec.get("sql_name", col_name)
        col = resolve_column(sql_name, spec, adult, pt_flat, schema)
        schema.columns.append(col)

    # Group by table, preserving declared order.
    tables: dict[str, list[Column]] = {}
    for col in schema.columns:
        tables.setdefault(col.table, []).append(col)

    out = [HEADER]

    # ENUMs first (in the order they're introduced).
    seen = set()
    for col in schema.columns:
        if col.enum_type_name and col.enum_type_name not in seen:
            out.append(f"-- ENUM for column {col.name} ({col.table})")
            out.append(emit_enum(col))
            out.append("")
            seen.add(col.enum_type_name)

    out.append(BATCH_LOG_DDL)

    # Tables in a specific order so FKs work.
    order = ["facilities", "therapists", "patients_adult",
             "treatments", "assessments", "process_ratings"]
    for t in order:
        if t in tables:
            out.append("")
            out.append(f"-- Table: {t}")
            out.append(emit_table(t, tables[t]))

    out.append(FOOTER)

    if schema.warnings:
        sys.stderr.write("\n".join(f"WARN: {w}" for w in schema.warnings) + "\n")

    return "\n".join(out)


# --------------------------------------------------------------------------- #
# CLI                                                                         #
# --------------------------------------------------------------------------- #

def main() -> int:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--adult",   required=True, type=Path)
    p.add_argument("--pt",      required=True, type=Path)
    p.add_argument("--mapping", required=True, type=Path)
    p.add_argument("--out",     required=True, type=Path)
    args = p.parse_args()

    sql = generate(args.adult, args.pt, args.mapping)
    args.out.write_text(sql, encoding="utf-8")
    sys.stderr.write(f"wrote {args.out} ({len(sql):,} bytes)\n")
    return 0


if __name__ == "__main__":
    sys.exit(main())
