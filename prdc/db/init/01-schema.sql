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


-- ENUM for column art_und_setting_einrichtung (facilities)
CREATE TYPE art_und_setting_einrichtung_t AS ENUM (
    'psychotherapeutische HSA',
    'Psychotherapeutische Ausbildungsambulanz',
    'Institutsambulanz',
    'Medizinisches Versorgungszentrum (MVZ)',
    'Klinik für Psychiatrie und Psychotherapie',
    'Klinik für Kinder und Jugendpsychiatrie',
    'Klinik für Psychosomatische Medizin und Psychotherapie',
    'Tagesklinik für Psychiatrie und Psychotherapie',
    'Tagesklinik für Psychosomatische Medizin und Psychotherapie'
);

-- ENUM for column traeger (facilities)
CREATE TYPE traeger_t AS ENUM (
    'nicht-akademischer öffentlicher Träger',
    'akademischer öffentlicher Träger',
    'freigemeinnütziger Träger (z.B. Kirche, Stiftungen)',
    'privater Träger',
    'sonstiges'
);

-- ENUM for column behandelte_altersgruppe (facilities)
CREATE TYPE behandelte_altersgruppe_t AS ENUM (
    'Kinder- und Jugendliche',
    'Erwachsene'
);

-- ENUM for column angebotene_pt_verfahren (facilities)
CREATE TYPE angebotene_pt_verfahren_t AS ENUM (
    'Verhaltenstherapie',
    'Psychoanalyse',
    'tiefenpsychologisch fundierte Psychotherapie',
    'systemische Psychotherapie'
);

-- ENUM for column pt_setting (facilities)
CREATE TYPE pt_setting_t AS ENUM (
    'Gruppentherapie',
    'Einzeltherapie',
    'Mehrpersonensetting'
);

-- ENUM for column Geschlecht_T (therapists)
CREATE TYPE geschlecht_t_t AS ENUM (
    'Männlich',
    'Weiblich',
    'Divers'
);

-- ENUM for column PsychThG_Fassung (therapists)
CREATE TYPE psychthg_fassung_t AS ENUM (
    'vor 1999',
    'seit 1999',
    'seit 2020'
);

-- ENUM for column Fachkunde_Schwerpunkt (therapists)
CREATE TYPE fachkunde_schwerpunkt_t AS ENUM (
    'Kinder- und Jugendlichenpsychotherapie',
    'Psychotherapie für Erwachsene'
);

-- ENUM for column therapeutische_Orientierung (therapists)
CREATE TYPE therapeutische_orientierung_t AS ENUM (
    'Verhaltenstherapie',
    'Psychoanalyse',
    'tiefenpsychologisch fundierte Psychotherapie',
    'systemische Psychotherapie',
    'Schematherapie',
    'DBT',
    'Traumatherapie',
    'CBASP',
    'IPT',
    'Triple P',
    'Gruppenzusatzqualifikation',
    'Achtsamkeitsbasierte Verfahren'
);

-- ENUM for column Sex (patients_adult)
CREATE TYPE sex_t AS ENUM (
    'männlich',
    'weiblich',
    'divers'
);

-- ENUM for column Gender (patients_adult)
CREATE TYPE gender_t AS ENUM (
    'männlich',
    'weiblich',
    'nicht-binär',
    'trans*',
    'queer',
    'einem anderen und zwar: ___'
);

-- ENUM for column Familienstand (patients_adult)
CREATE TYPE familienstand_t AS ENUM (
    'Ledig',
    'Partnerschaft',
    'Verheiratet',
    'Eingetragene Partnerschaft',
    'Getrennt lebend bzw. im Trennungsjahr',
    'Aufgehobene eingetragene Partnerschaft',
    'Geschieden',
    'Verwitwet'
);

-- ENUM for column Mehrlingsgeburt (patients_adult)
CREATE TYPE mehrlingsgeburt_t AS ENUM (
    'Ja',
    'Nein'
);

-- ENUM for column Erwerbsunfaehig (patients_adult)
CREATE TYPE erwerbsunfaehig_t AS ENUM (
    '(1) Nein',
    '(2) Ja, weniger als 5 Tage',
    '(3) Ja, weniger als 10 Tage',
    '(4) Ja, weniger als 20 Tage',
    '(5) Ja, weniger als 50 Tage',
    '(6) Ja, mindestens 50 Tage oder mehr'
);

-- ENUM for column Nettoeinkommen_jahr_hh (patients_adult)
CREATE TYPE nettoeinkommen_jahr_hh_t AS ENUM (
    'Unter 6.000 Euro',
    '6.000-12.000 Euro',
    '12.000-18.000 Euro',
    '18.000-24.000 Euro',
    '24.000-30.000 Euro',
    '30.000-36.000 Euro',
    '36.000-42.000 Euro',
    '42.000-48.000 Euro',
    '48.000-54.000 Euro',
    '54.000-60.000 Euro',
    '60.000-80.000 Euro',
    '80.000-120.000 Euro',
    '120.000-250.000 Euro',
    '250.000-500.000 Euro',
    'Mehr als 500.000 Euro'
);

-- ENUM for column Sport_aktuell (patients_adult)
CREATE TYPE sport_aktuell_t AS ENUM (
    'Keine sportliche Betätigung',
    'Weniger als 1 Stunde in der Woche',
    'Regelmäßig 1 bis unter 2 Stunden pro Woche',
    'Regelmäßig 2 bis unter 4 Stunden pro Woche',
    'Regelmäßig 4 Stunden in der Woche und mehr'
);

-- ENUM for column MNspiel (patients_adult)
CREATE TYPE mnspiel_t AS ENUM (
    'Gar nicht',
    'Bis zu einer Stunde',
    'Bis zu 2 Stunden',
    'Bis zu 3 Stunden',
    'Bis zu 4 Stunden',
    'Mehr als 4 Stunden'
);

-- ENUM for column Diagnose_fam_dich (patients_adult)
CREATE TYPE diagnose_fam_dich_t AS ENUM (
    'Ja',
    'Nein',
    'Weiß nicht'
);

-- ENUM for column MEHM_GZ (patients_adult)
CREATE TYPE mehm_gz_t AS ENUM (
    'Sehr gut',
    'Gut',
    'Zufriedenstellend',
    'Weniger gut',
    'Schlecht'
);

-- ENUM for column MEHM_GALI_1 (patients_adult)
CREATE TYPE mehm_gali_1_t AS ENUM (
    '... nicht eingeschränkt?',
    '... mäßig eingeschränkt?',
    '... stark eingeschränkt?'
);

-- ENUM for column SoC_1 (patients_adult)
CREATE TYPE soc_1_t AS ENUM (
    'Überhaupt nicht',
    'Ein wenig',
    'Mäßig',
    'Stark',
    'Sehr stark'
);

-- ENUM for column LONELINESS_SIDF (patients_adult)
CREATE TYPE loneliness_sidf_t AS ENUM (
    'Nie',
    'Selten',
    'Manchmal',
    'Häufig',
    'Immer'
);

-- ENUM for column IPSM_24 (patients_adult)
CREATE TYPE ipsm_24_t AS ENUM (
    'Trifft gar nicht zu',
    'Trifft eher nicht zu',
    'Trifft eher zu',
    'Trifft sehr zu'
);

-- ENUM for column CGI_S_sr (patients_adult)
CREATE TYPE cgi_s_sr_t AS ENUM (
    'Kann ich nicht beurteilen',
    'Ich bin überhaupt nicht krank',
    'Ich bin mir nicht sicher, ob ich psychisch krank bin',
    'Ich bin nur leicht krank',
    'Ich bin mäßig krank',
    'Ich bin deutlich krank',
    'Ich bin schwer krank',
    'Ich bin extrem schwer krank'
);

-- ENUM for column BESCA_DM_Oa (patients_adult)
CREATE TYPE besca_dm_oa_t AS ENUM (
    'Nie',
    'Selten',
    'Häufig',
    'Sehr oft'
);

-- ENUM for column Therapie_Setting (treatments)
CREATE TYPE therapie_setting_t AS ENUM (
    'ambulant',
    'teilstationär',
    'stationär'
);

-- ENUM for column Therapie_Format (treatments)
CREATE TYPE therapie_format_t AS ENUM (
    'Einzeltherapie',
    'Gruppentherapie',
    'Mehrpersonensetting',
    'sonstiges'
);

-- ENUM for column Durchschnittliche_Frequenz (treatments)
CREATE TYPE durchschnittliche_frequenz_t AS ENUM (
    '4-5x/Woche',
    '2-3x/Woche',
    '1x/Woche',
    '2x/Monat',
    '1x/Monat',
    '1x/Quartal'
);

-- ENUM for column Behandlungsende_Art (treatments)
CREATE TYPE behandlungsende_art_t AS ENUM (
    'Reguläres Therapieende',
    'Abbruch von TherapeutInnenseite',
    'Abbruch von PatientInnenseite',
    'keine Kostenübernahme',
    'Unterbrechung'
);

-- ENUM for column P_Faktor_Item_1 (assessments)
CREATE TYPE p_faktor_item_1_t AS ENUM (
    'Trifft gar nicht zu',
    'Trifft eher nicht zu',
    'teils/teils',
    'Trifft eher zu',
    'Trifft sehr zu'
);

-- ENUM for column Spectra_SOM (assessments)
CREATE TYPE spectra_som_t AS ENUM (
    'Nein, überhaupt nicht',
    'Kaum, selten, an wenigen Tagen',
    'Leicht, an mehreren Tagen',
    'Mittel, an mehr als der Hälfte der Tage',
    'Schwer, an fast jedem Tag'
);

-- ENUM for column Spectra_INT_Depression_Anhedonie (assessments)
CREATE TYPE spectra_int_depression_anhedonie_t AS ENUM (
    'Überhaupt nicht',
    'An einzelnen Tagen',
    'An mehr als der Hälfte der Tage',
    'Beinahe jeden Tag'
);

-- ENUM for column Spectra_INT_Item_2 (assessments)
CREATE TYPE spectra_int_item_2_t AS ENUM (
    'Traf gar nicht auf mich zu',
    'Traf bis zu einem gewissen Grad auf mich zu oder manchmal',
    'Traf in beträchtlichem Maße auf mich zu oder ziemlich oft',
    'Traf sehr stark auf mich zu oder die meiste Zeit'
);

-- ENUM for column Spectra_INT_Item_11 (assessments)
CREATE TYPE spectra_int_item_11_t AS ENUM (
    'Ja, mir persönlich zugestoßen',
    'Ja, Zeuge davon gewesen',
    'Ja, davon erfahren',
    'Ja, im Rahmen meines Berufs',
    'unsicher',
    'Nein, trifft nicht zu'
);

-- ENUM for column EAT26D_23 (assessments)
CREATE TYPE eat26d_23_t AS ENUM (
    'Immer',
    'Sehr oft',
    'Oft',
    'Manchmal',
    'Selten',
    'Nie'
);

-- ENUM for column Spectra_EXT_Item_10 (assessments)
CREATE TYPE spectra_ext_item_10_t AS ENUM (
    'Überhaupt nicht',
    'Selten, an wenigen Tagen',
    'An mehreren Tagen',
    'An mehr als der Hälfte der Tage',
    'An fast jedem Tag'
);

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


-- Table: facilities
CREATE TABLE facilities (
    "facility_id" TEXT PRIMARY KEY,
    "plz_einrichtung" plz_de,
    "art_und_setting_einrichtung" art_und_setting_einrichtung_t,
    "traeger" traeger_t,
    "kostenuebername" TEXT[] CHECK ("kostenuebername" <@ ARRAY['gesetzliche KV', 'private KV', 'Rentenversicherung', 'Selbstzahler:innen', 'Beihilfe', 'Patient:innen ohne KV ( Übernahme durch Einrichtung, Stiftungen, Vereine)']::text[]),
    "behandelte_altersgruppe" behandelte_altersgruppe_t[],
    "groesse_einzugsgebiet" INTEGER CHECK ("groesse_einzugsgebiet" >= 0),
    "n_fachpsychotherapeuten" SMALLINT CHECK ("n_fachpsychotherapeuten" >= 0),
    "n_fachärzte" SMALLINT CHECK ("n_fachärzte" >= 0),
    "bettenanzahl" SMALLINT CHECK ("bettenanzahl" >= 0),
    "behandlungsfaelle_pro_jahr" INTEGER CHECK ("behandlungsfaelle_pro_jahr" >= 0),
    "patienten_pro_jahr" INTEGER CHECK ("patienten_pro_jahr" >= 0),
    "angebotene_pt_verfahren" angebotene_pt_verfahren_t[],
    "angebot_stoerungsspezifisch" TEXT[] CHECK ("angebot_stoerungsspezifisch" <@ ARRAY['Psychotische Störung', 'Depressive Störung', 'Bipolare affektive Störung', 'Angststörung', 'Zwangsstörung', 'Posttraumatische Belastungsstörung', 'Somatoforme Störung', 'Essstörung', 'Persönlichkeits- und/oder Verhaltensstörung', 'Entwicklungsstörung', 'Einfache Aktivitäts- und Aufmerksamkeitsstörung', 'Autismus-Spektrum-Störung', 'Substanzabhängigkeit (z.B. Alkohol, Medikamente)', 'Demenzielle Erkrankung', 'Keine der oben genannten sondern: ___  (offenes Textfeld zwischen 1-500 Zeichen)']::text[]),
    "pt_setting" pt_setting_t[],
    "first_seen_at"  TIMESTAMPTZ NOT NULL DEFAULT now(),
    "first_seen_batch"  UUID REFERENCES batch_log(batch_id)
);

-- Table: therapists
CREATE TABLE therapists (
    "facility_id" TEXT NOT NULL REFERENCES facilities(facility_id),
    "therapist_id" TEXT PRIMARY KEY,
    "Geburtsjahr_T" plausible_year NOT NULL,
    "Geschlecht_T" geschlecht_t_t NOT NULL,
    "hat_Approbation_PsychThG" BOOLEAN NOT NULL,
    "Approbation_Jahr" plausible_year,
    "PsychThG_Fassung" psychthg_fassung_t,
    "hat_Fachkunde" BOOLEAN NOT NULL,
    "Fachkunde_Schwerpunkt" fachkunde_schwerpunkt_t[],
    "Fachkunde_Richtlinienverfahren" angebotene_pt_verfahren_t[],
    "therapeutische_Orientierung" therapeutische_orientierung_t[],
    "Sitzungen_pro_Woche_letztes_Jahr" NUMERIC(4,1) CHECK ("Sitzungen_pro_Woche_letztes_Jahr" BETWEEN 0 AND 80),
    "first_seen_at"  TIMESTAMPTZ NOT NULL DEFAULT now(),
    "first_seen_batch"  UUID REFERENCES batch_log(batch_id),
    CHECK ((("hat_Approbation_PsychThG" = false AND "Approbation_Jahr" IS NULL)  OR ("hat_Approbation_PsychThG" = true AND "Approbation_Jahr" IS NOT NULL)))
);
CREATE INDEX therapists_facility_id_idx ON therapists ("facility_id");

-- Table: patients_adult
CREATE TABLE patients_adult (
    "patient_id" TEXT PRIMARY KEY,
    "PLZv2" plz_de,
    "Geb_Monat" SMALLINT CHECK ("Geb_Monat" BETWEEN 1 AND 12),
    "Geb_jahr" plausible_year NOT NULL,
    "Gewicht" NUMERIC(4,1) CHECK ("Gewicht" BETWEEN 20 AND 300),
    "Groesse" SMALLINT CHECK ("Groesse" BETWEEN 100 AND 230),
    "Sex" sex_t NOT NULL,
    "Gender" gender_t[],
    "Familienstand" familienstand_t[],
    "Kinder" BOOLEAN,
    "Kinder_Anzahl" SMALLINT CHECK ("Kinder_Anzahl" BETWEEN 0 AND 20),
    "Migration_dich" BOOLEAN,
    "Mehrlingsgeburt" mehrlingsgeburt_t,
    "Haushalt_anzahl" SMALLINT CHECK ("Haushalt_anzahl" BETWEEN 1 AND 20),
    "Schulabschluss" TEXT CHECK ("Schulabschluss" IN ('Abitur, fachgebundene Hochschulreife oder Fachhochschulreife', 'Realschulabschluss, Mittlere Reife, Polytechnische Oberschule mit Abschluss der 10. Klasse', 'Haupt-/Volksschulabschluss, Polytechnische Oberschule mit Abschluss der 8. oder 9. Klasse', 'Abschluss nach höchstens 7 Jahren Schulbesuch', 'Von der Schule abgegangen ohne Abschluss', 'Keinen, ich bin noch SchülerIn')),
    "Bildungsabschluss" TEXT CHECK ("Bildungsabschluss" IN ('Hochschul- oder Fachhochschulabschluss: Promotion', 'Hochschul- oder Fachhochschulabschluss: Master (oder vergleichbar)', 'Hochschul- oder Fachhochschulabschluss: Bachelor', 'Fachhochschule, Ingenieurschule, duale Hochschule oder Berufsakademie/Verwaltungsfachhochschule', 'Fachschule (Meister-, Technikerschule, Berufs- oder Fachakademie), Fachschule der DDR oder Fachakademie (Bayern)', 'Ausbildung an Berufsfachschule, Handelsschule (beruflich-schulische Ausbildung)', 'Lehre (beruflich-betriebliche Ausbildung)', 'Keinen oder noch in beruflicher Ausbildung (SchülerIn, Azubi, Berufsvorbereitungsjahr, PraktikantIn, StudentIn)', 'Keinen Berufsabschluss (und nicht in Ausbildung)')),
    "Berufstaetig" TEXT CHECK ("Berufstaetig" IN ('Ich bin vollzeit-erwerbstätig mit einer wöchentlichen Arbeitszeit von 35 Stunden und mehr', 'Ich bin teilzeit-erwerbstätig mit einer wöchentlichen Arbeitszeit von 15 bis 34 Stunden', 'Ich bin teilzeit- oder stundenweise erwerbstätig mit einer wöchentlichen Arbeitszeit unter 15 Stunden', 'Ich bin arbeitslos', 'Ich bin im Ruhestand oder Vorruhestand', 'Ich bin aus anderen Gründen nicht erwerbstätig', '(-2) Keine Angaben')),
    "Erwerbsunfaehig" erwerbsunfaehig_t,
    "Erwerbsunfaehig_psy" erwerbsunfaehig_t,
    "Nettoeinkommen_jahr_hh" nettoeinkommen_jahr_hh_t,
    "SSS" vas_10,
    "Sport_aktuell" sport_aktuell_t,
    "MNspiel" mnspiel_t,
    "MNsoznw" mnspiel_t,
    "Erstkontakt" BOOLEAN,
    "Erstkontakt_alter" SMALLINT CHECK ("Erstkontakt_alter" BETWEEN 0 AND 120),
    "Behandlung_dich" mehrlingsgeburt_t,
    "Diagnose_selbst_dich" mehrlingsgeburt_t,
    "Diagnose_PD" BOOLEAN,
    "Diagnose_DD" BOOLEAN,
    "Diagnose_BD" BOOLEAN,
    "Diagnose_AD" BOOLEAN,
    "Diagnose_OCD" BOOLEAN,
    "Diagnose_PTSD" BOOLEAN,
    "Diagnose_SD" BOOLEAN,
    "Diagnose_ED" BOOLEAN,
    "Diagnose_PBD" BOOLEAN,
    "Diagnose_DMD" BOOLEAN,
    "Diagnose_ADHD" BOOLEAN,
    "Diagnose_ASD" BOOLEAN,
    "Diagnose_SUD" BOOLEAN,
    "Diagnose_D" BOOLEAN,
    "Diagnose_fam_dich" diagnose_fam_dich_t,
    "Suizidversuch" BOOLEAN,
    "Suizidversuche_anzahl" SMALLINT CHECK ("Suizidversuche_anzahl" >= 0),
    "Hilfesystem_Erfahrung" BOOLEAN,
    "ACE_8" mehrlingsgeburt_t,
    "MEHM_GZ" mehm_gz_t,
    "MEHM_GALI_1" mehm_gali_1_t,
    "EQ5D5L_3" TEXT CHECK ("EQ5D5L_3" IN ('Ich habe keine Probleme, meinen alltäglichen Tätigkeiten nachzugehen', 'Ich habe leichte Probleme, meinen alltäglichen Tätigkeiten nachzugehen', 'Ich habe mäßige Probleme, meinen alltäglichen Tätigkeiten nachzugehen', 'Ich habe große Probleme, meinen alltäglichen Tätigkeiten nachzugehen', 'Ich bin nicht in der Lage, meinen alltäglichen Tätigkeiten nachzugehen')),
    "vas_PSYBEL" vas_100,
    "vas_SOZEX100" vas_100,
    "vas_SOZDIS100" vas_100,
    "vas_STIGMA100" vas_100,
    "vas_SATIS100" vas_100,
    "SoC_1" soc_1_t,
    "LONELINESS_SIDF" loneliness_sidf_t,
    "IPSM_24" ipsm_24_t,
    "CGI_S_sr" cgi_s_sr_t,
    "BESCA_DM_Oa" besca_dm_oa_t,
    "PNRS_sucht_mutter" BOOLEAN,
    "PNRS_psych_mutter" BOOLEAN,
    "PNRS_rauchen_schwangerschaft" BOOLEAN,
    "CTS_emotionale_misshandlung" SMALLINT CHECK ("CTS_emotionale_misshandlung" BETWEEN 0 AND 4),
    "CTS_koerperliche_misshandlung" SMALLINT CHECK ("CTS_koerperliche_misshandlung" BETWEEN 0 AND 4),
    "CTS_sexueller_missbrauch" SMALLINT CHECK ("CTS_sexueller_missbrauch" BETWEEN 0 AND 4),
    "CTS_emotionale_vernachlaessigung" SMALLINT CHECK ("CTS_emotionale_vernachlaessigung" BETWEEN 0 AND 4),
    "CTS_koerperliche_vernachlaessigung" SMALLINT CHECK ("CTS_koerperliche_vernachlaessigung" BETWEEN 0 AND 4),
    "first_seen_at"  TIMESTAMPTZ NOT NULL DEFAULT now(),
    "first_seen_batch"  UUID REFERENCES batch_log(batch_id),
    CHECK ((("Kinder" = false AND COALESCE("Kinder_Anzahl", 0) = 0)  OR ("Kinder" = true AND "Kinder_Anzahl" IS NOT NULL AND "Kinder_Anzahl" > 0)  OR ("Kinder" IS NULL)))
);

-- Table: treatments
CREATE TABLE treatments (
    "patient_id" TEXT NOT NULL REFERENCES patients_adult(patient_id) UNIQUE,
    "therapist_id" TEXT NOT NULL REFERENCES therapists(therapist_id),
    "facility_id" TEXT NOT NULL REFERENCES facilities(facility_id),
    "treatment_id" TEXT PRIMARY KEY,
    "Therapie_Setting" therapie_setting_t[] NOT NULL CHECK (array_length("Therapie_Setting", 1) >= 1),
    "Therapie_Format" therapie_format_t[] NOT NULL CHECK (array_length("Therapie_Format", 1) >= 1),
    "Video_Sitzungen" BOOLEAN NOT NULL,
    "Sprache_der_Therapie" CHAR(3) NOT NULL,
    "Dolmetscher_Einbezug" BOOLEAN NOT NULL,
    "Sprechstunden_vor_Behandlung" SMALLINT CHECK ("Sprechstunden_vor_Behandlung" >= 0),
    "Probatorische_Sitzungen" SMALLINT CHECK ("Probatorische_Sitzungen" >= 0),
    "Einzeltherapie_50min" SMALLINT CHECK ("Einzeltherapie_50min" >= 0),
    "Gruppentherapie_100min" SMALLINT CHECK ("Gruppentherapie_100min" >= 0),
    "Bezugspersonengespraeche" SMALLINT CHECK ("Bezugspersonengespraeche" >= 0),
    "Durchschnittliche_Frequenz" durchschnittliche_frequenz_t,
    "Behandlungsbeginn" DATE NOT NULL,
    "Behandlungsende_Datum" DATE NOT NULL,
    "Behandlungsende_Art" behandlungsende_art_t NOT NULL,
    "TherapeutInnen_Wechsel" BOOLEAN NOT NULL,
    "batch_id"  UUID NOT NULL REFERENCES batch_log(batch_id),
    CHECK ("Behandlungsende_Datum" >= "Behandlungsbeginn")
);
CREATE INDEX treatments_patient_id_idx ON treatments ("patient_id");
CREATE INDEX treatments_therapist_id_idx ON treatments ("therapist_id");
CREATE INDEX treatments_facility_id_idx ON treatments ("facility_id");

-- Table: assessments
CREATE TABLE assessments (
    "assessment_id"  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    "batch_id"  UUID NOT NULL REFERENCES batch_log(batch_id),
    "treatment_id" TEXT NOT NULL REFERENCES treatments(treatment_id),
    "timepoint" timepoint_t NOT NULL,
    "assessed_at" DATE NOT NULL,
    "P_Faktor_Item_1" p_faktor_item_1_t,
    "P_Faktor_Item_2" p_faktor_item_1_t,
    "P_Faktor_Item_3" p_faktor_item_1_t,
    "P_Faktor_Item_4" p_faktor_item_1_t,
    "P_Faktor_Item_5" p_faktor_item_1_t,
    "P_Faktor_Item_6" p_faktor_item_1_t,
    "P_Faktor_Item_7" p_faktor_item_1_t,
    "P_Faktor_Item_8" p_faktor_item_1_t,
    "Spectra_SOM" spectra_som_t,
    "Spectra_INT_Depression_Anhedonie" spectra_int_depression_anhedonie_t,
    "Spectra_INT_Item_1" spectra_int_depression_anhedonie_t,
    "Spectra_INT_Item_2" spectra_int_item_2_t,
    "Spectra_INT_Item_3" spectra_int_depression_anhedonie_t,
    "Spectra_INT_Item_4" spectra_som_t,
    "Spectra_INT_Item_5" spectra_int_depression_anhedonie_t,
    "Spectra_INT_Item_6" spectra_int_item_2_t,
    "Spectra_INT_Item_7" spectra_int_item_2_t,
    "Spectra_INT_Angst_Panik" spectra_som_t,
    "Spectra_INT_Angst_Phobie" spectra_som_t,
    "Spectra_INT_Angst_Soziale_Phobie" p_faktor_item_1_t,
    "Spectra_INT_Item_11" spectra_int_item_11_t,
    "Spectra_INT_Item_12" spectra_int_item_11_t,
    "Spectra_INT_Essverhalten_Appetit" mehrlingsgeburt_t,
    "EatingDisorder_Bulimic" p_faktor_item_1_t,
    "EatingDisorder_Anorectic" p_faktor_item_1_t,
    "SNAQ_3" mehrlingsgeburt_t,
    "EAT26D_23" eat26d_23_t,
    "OCD" p_faktor_item_1_t,
    "Spectra_TD_Item_1" p_faktor_item_1_t,
    "Spectra_TD_Item_2" p_faktor_item_1_t,
    "Spectra_TD_Item_3" p_faktor_item_1_t,
    "Spectra_TD_Item_4" p_faktor_item_1_t,
    "Spectra_TD_Item_5" p_faktor_item_1_t,
    "Soziale_Anhedonie" spectra_som_t,
    "Spectra_DET_Item_2" spectra_int_item_2_t,
    "Spectra_DET_Item_3" TEXT CHECK ("Spectra_DET_Item_3" IN ('Stimmt überhaupt nicht', '2', '3', 'Neutral', '5', '6', 'Stimmt vollkommen')),
    "Spectra_DET_Item_4" p_faktor_item_1_t,
    "Spectra_DET_Item_5" p_faktor_item_1_t,
    "Spectra_EXT_Item_1" p_faktor_item_1_t,
    "Spectra_EXT_Item_2" p_faktor_item_1_t,
    "Spectra_EXT_Item_3" p_faktor_item_1_t,
    "Spectra_EXT_Item_4" p_faktor_item_1_t,
    "Spectra_EXT_Item_5" p_faktor_item_1_t,
    "Spectra_EXT_Item_6" p_faktor_item_1_t,
    "Spectra_EXT_Item_7" p_faktor_item_1_t,
    "Spectra_EXT_Item_8" p_faktor_item_1_t,
    "Aggressivitaet" spectra_som_t,
    "Spectra_EXT_Item_10" spectra_ext_item_10_t,
    "Spectra_EXT_Item_11" spectra_ext_item_10_t,
    "Spectra_EXT_Item_12" spectra_ext_item_10_t,
    "Spectra_EXT_Item_13" spectra_ext_item_10_t,
    "globales_Wohlbefinden_VAS" vas_100 NOT NULL,
    UNIQUE ("treatment_id", "timepoint")
);
CREATE INDEX assessments_treatment_id_idx ON assessments ("treatment_id");

-- Table: process_ratings
CREATE TABLE process_ratings (
    "process_id"  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    "batch_id"  UUID NOT NULL REFERENCES batch_log(batch_id),
    "treatment_id" TEXT NOT NULL REFERENCES treatments(treatment_id),
    "Alliance_Bond_Capacity_1" p_faktor_item_1_t,
    "Alliance_Bond_Capacity_2" p_faktor_item_1_t,
    "Alliance_Bond_Capacity_3" p_faktor_item_1_t,
    "Alliance_Bond_Capacity_4" p_faktor_item_1_t,
    "Persuasiveness_Credibility_1" p_faktor_item_1_t,
    "Persuasiveness_Credibility_2" p_faktor_item_1_t,
    "Goal_Consensus_1" p_faktor_item_1_t,
    "Goal_Consensus_2" p_faktor_item_1_t,
    "Goal_Consensus_3" p_faktor_item_1_t,
    "Rupture_Repair_1" p_faktor_item_1_t,
    "Rupture_Repair_2" p_faktor_item_1_t,
    "Rupture_Repair_3" p_faktor_item_1_t,
    "Ressource_Activation_1" p_faktor_item_1_t,
    "Ressource_Activation_2" p_faktor_item_1_t,
    "Ressource_Activation_3" p_faktor_item_1_t,
    "Congruence_1" p_faktor_item_1_t,
    "Congruence_2" p_faktor_item_1_t,
    "Managing_Countertransference_1" p_faktor_item_1_t,
    "Managing_Countertransference_2" p_faktor_item_1_t,
    "CBT_1" p_faktor_item_1_t,
    "CBT_2" p_faktor_item_1_t,
    "CBT_3" p_faktor_item_1_t,
    "DYN_1" p_faktor_item_1_t,
    "DYN_2" p_faktor_item_1_t,
    "DYN_3" p_faktor_item_1_t,
    "timepoint" timepoint_t NOT NULL,
    CHECK (timepoint != 'pre'),
    UNIQUE ("treatment_id", "timepoint")
);
CREATE INDEX process_ratings_treatment_id_idx ON process_ratings ("treatment_id");

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
