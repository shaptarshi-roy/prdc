-- =====================================================================
--  Researcher views — k-anonymity + l-diversity
--
--  Researchers never query the base tables. They query these views,
--  which:
--
--    1. GENERALIZE the quasi-identifiers
--         - birth year  → 10-year band
--         - PLZv2        → 3-digit prefix
--         - Sex          → kept as-is (already coarse)
--
--    2. SUPPRESS any row whose generalized quasi-identifier combination
--       (the "equivalence class") fails EITHER guarantee:
--         - k-anonymity: the class has < K members            → hidden
--         - l-diversity: the class has < L distinct diagnosis
--                        profiles                              → hidden
--
--  Why both? k-anonymity stops an individual being singled out by
--  {age band, postcode, sex}. But if every patient in a class shares
--  the same diagnosis, knowing someone is in the class reveals their
--  diagnosis even though they were never individually identified — the
--  "homogeneity attack". Because diagnosis IS exposed in the researcher
--  view (it is a core MDS-PT analysis variable), l-diversity is required,
--  not optional.
--
--  PARAMETERS (set by the data protection officer, not by default):
--    K = 5   minimum equivalence-class size
--    L = 3   minimum distinct diagnosis profiles per class
--
--  To change them, edit the two literals in the final WHERE clause of
--  each view and reload. They are called out in comments below.
--
--  SCOPE NOTE: This enforces *distinct* l-diversity — the simplest of
--  the three standard variants. Stronger guarantees (entropy l-diversity,
--  t-closeness) are NOT computed here; they are provided per-study via an
--  offline ARX export when an ethics board requires them. See the
--  architecture walkthrough, section "Researcher views: k-anonymity and
--  l-diversity".
-- =====================================================================


-- ---------------------------------------------------------------------
--  Helper view: one row per patient with a canonical diagnosis profile.
--
--  The 14 Diagnose_* boolean flags are collapsed into a single text
--  label like "AD+DD+PTSD" (alphabetical, '+'-joined). This profile is
--  the sensitive attribute we diversify over: two patients with an
--  identical set of diagnoses share a profile; any difference makes
--  them distinct. A patient with no flags set gets the profile 'none'.
-- ---------------------------------------------------------------------
CREATE VIEW v_patient_diag_profile AS
SELECT
    p.patient_id,
    p."PLZv2"     AS plz,
    p."Geb_jahr"  AS birth_year,
    p."Sex"       AS sex,
    NULLIF(
        concat_ws('+',
            CASE WHEN p."Diagnose_AD"   THEN 'AD'   END,
            CASE WHEN p."Diagnose_ADHD" THEN 'ADHD' END,
            CASE WHEN p."Diagnose_ASD"  THEN 'ASD'  END,
            CASE WHEN p."Diagnose_BD"   THEN 'BD'   END,
            CASE WHEN p."Diagnose_D"    THEN 'D'    END,
            CASE WHEN p."Diagnose_DD"   THEN 'DD'   END,
            CASE WHEN p."Diagnose_DMD"  THEN 'DMD'  END,
            CASE WHEN p."Diagnose_ED"   THEN 'ED'   END,
            CASE WHEN p."Diagnose_OCD"  THEN 'OCD'  END,
            CASE WHEN p."Diagnose_PBD"  THEN 'PBD'  END,
            CASE WHEN p."Diagnose_PD"   THEN 'PD'   END,
            CASE WHEN p."Diagnose_PTSD" THEN 'PTSD' END,
            CASE WHEN p."Diagnose_SD"   THEN 'SD'   END,
            CASE WHEN p."Diagnose_SUD"  THEN 'SUD'  END
        ),
        ''
    ) AS diagnosis_profile
FROM patients_adult p;


-- ---------------------------------------------------------------------
--  Equivalence-class metrics.
--
--  Generalize the quasi-identifiers, then for each generalized class
--  compute its size (for k) and its number of distinct diagnosis
--  profiles (for l). Computed as a grouped aggregate, then joined back
--  to the per-patient rows — this avoids COUNT(DISTINCT) as a window
--  function, which is awkward / unsupported across Postgres versions.
-- ---------------------------------------------------------------------
CREATE VIEW v_patient_generalized AS
SELECT
    patient_id,
    diagnosis_profile,
    -- birth year → start of its 10-year band (1988 → 1980)
    (birth_year / 10) * 10                       AS birth_decade,
    -- PLZ → 3-digit prefix, zero-padded to a stable 5-char base first
    left(lpad(plz::text, 5, '0'), 3)             AS plz_prefix,
    sex
FROM v_patient_diag_profile;


CREATE VIEW v_class_metrics AS
SELECT
    birth_decade,
    plz_prefix,
    sex,
    count(*)                          AS class_size,
    count(DISTINCT diagnosis_profile) AS class_diversity
FROM v_patient_generalized
GROUP BY birth_decade, plz_prefix, sex;


-- ---------------------------------------------------------------------
--  THE RESEARCHER VIEW.
--
--  Joins each patient's generalized record to its class metrics and
--  keeps the row ONLY IF the class satisfies both guarantees.
--
--  >>> K and L ARE SET HERE <<<   (class_size >= K, class_diversity >= L)
-- ---------------------------------------------------------------------
CREATE VIEW researcher_patients AS
SELECT
    g.birth_decade,
    g.plz_prefix,
    g.sex,
    g.diagnosis_profile
    -- patient_id, exact birth year, full PLZ are deliberately NOT exposed
FROM v_patient_generalized g
JOIN v_class_metrics m
  ON  m.birth_decade = g.birth_decade
  AND m.plz_prefix   = g.plz_prefix
  AND m.sex          = g.sex
WHERE m.class_size      >= 5      -- K = 5  (k-anonymity)
  AND m.class_diversity >= 3;     -- L = 3  (distinct l-diversity)


-- ---------------------------------------------------------------------
--  Outcome view: assessments joined to the SAME suppressed population.
--
--  A researcher analysing outcome (e.g. globales_Wohlbefinden_VAS over
--  timepoints) only sees assessments belonging to patients who survived
--  the k/l suppression above. The join to researcher_patients is what
--  enforces that — there is no path to an assessment whose patient was
--  suppressed.
--
--  Note we expose the generalized quasi-identifiers + diagnosis profile
--  + outcome, never the patient_id.
-- ---------------------------------------------------------------------
CREATE VIEW researcher_outcomes AS
SELECT
    rp.birth_decade,
    rp.plz_prefix,
    rp.sex,
    rp.diagnosis_profile,
    a."timepoint",
    a."globales_Wohlbefinden_VAS"
FROM v_patient_generalized g
JOIN v_class_metrics m
  ON  m.birth_decade = g.birth_decade
  AND m.plz_prefix   = g.plz_prefix
  AND m.sex          = g.sex
JOIN researcher_patients rp
  ON  rp.birth_decade      = g.birth_decade
  AND rp.plz_prefix        = g.plz_prefix
  AND rp.sex               = g.sex
  AND rp.diagnosis_profile IS NOT DISTINCT FROM g.diagnosis_profile
JOIN treatments  t ON t."patient_id"   = g.patient_id
JOIN assessments a ON a."treatment_id" = t."treatment_id"
WHERE m.class_size      >= 5
  AND m.class_diversity >= 3;


-- ---------------------------------------------------------------------
--  Grants. Researcher sees ONLY the researcher_* views, never the
--  intermediate v_* views and never the base tables.
-- ---------------------------------------------------------------------
GRANT SELECT ON researcher_patients TO researcher;
GRANT SELECT ON researcher_outcomes TO researcher;
-- The v_* views are internal scaffolding; researcher gets no grant on them.
