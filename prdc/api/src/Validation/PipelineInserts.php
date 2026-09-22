<?php
declare(strict_types=1);

namespace App\Validation;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

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

    /** Insert one row into facilities. */
    private function insertFacility(array $row, Uuid $batchId): void
    {
        $data = [
            'facility_id' => $this->strOrNull($row['facility_id'] ?? ''),
            'plz_einrichtung' => $this->intOrNull($row['plz_einrichtung'] ?? ''),
            'art_und_setting_einrichtung' => $this->strOrNull($row['art_und_setting_einrichtung'] ?? ''),
            'traeger' => $this->strOrNull($row['traeger'] ?? ''),
            'kostenuebername' => $this->toArray($row['kostenuebername'] ?? ''),
            'behandelte_altersgruppe' => $this->toArray($row['behandelte_altersgruppe'] ?? ''),
            'groesse_einzugsgebiet' => $this->intOrNull($row['groesse_einzugsgebiet'] ?? ''),
            'n_fachpsychotherapeuten' => $this->intOrNull($row['n_fachpsychotherapeuten'] ?? ''),
            'n_fachärzte' => $this->intOrNull($row['n_fachärzte'] ?? ''),
            'bettenanzahl' => $this->intOrNull($row['bettenanzahl'] ?? ''),
            'behandlungsfaelle_pro_jahr' => $this->intOrNull($row['behandlungsfaelle_pro_jahr'] ?? ''),
            'patienten_pro_jahr' => $this->intOrNull($row['patienten_pro_jahr'] ?? ''),
            'angebotene_pt_verfahren' => $this->toArray($row['angebotene_pt_verfahren'] ?? ''),
            'angebot_stoerungsspezifisch' => $this->toArray($row['angebot_stoerungsspezifisch'] ?? ''),
            'pt_setting' => $this->toArray($row['pt_setting'] ?? ''),
            'first_seen_batch' => $batchId,
        ];

        $types = [
            'kostenuebername' => Connection::PARAM_STR_ARRAY,
            'behandelte_altersgruppe' => Connection::PARAM_STR_ARRAY,
            'angebotene_pt_verfahren' => Connection::PARAM_STR_ARRAY,
            'angebot_stoerungsspezifisch' => Connection::PARAM_STR_ARRAY,
            'pt_setting' => Connection::PARAM_STR_ARRAY,
            'first_seen_batch' => 'uuid',
        ];

        $this->db->insert('facilities', $data, $types);
    }

    /** Insert one row into therapists. */
    private function insertTherapist(array $row, Uuid $batchId): void
    {
        $data = [
            'facility_id' => $row['facility_id'],
            'therapist_id' => $this->strOrNull($row['therapist_id'] ?? ''),
            'Geburtsjahr_T' => $this->intOrNull($row['Geburtsjahr_T'] ?? ''),
            'Geschlecht_T' => $this->strOrNull($row['Geschlecht_T'] ?? ''),
            'hat_Approbation_PsychThG' => $this->boolOrNull($row['hat_Approbation_PsychThG'] ?? ''),
            'Approbation_Jahr' => $this->intOrNull($row['Approbation_Jahr'] ?? ''),
            'PsychThG_Fassung' => $this->strOrNull($row['PsychThG_Fassung'] ?? ''),
            'hat_Fachkunde' => $this->boolOrNull($row['hat_Fachkunde'] ?? ''),
            'Fachkunde_Schwerpunkt' => $this->toArray($row['Fachkunde_Schwerpunkt'] ?? ''),
            'Fachkunde_Richtlinienverfahren' => $this->toArray($row['Fachkunde_Richtlinienverfahren'] ?? ''),
            'therapeutische_Orientierung' => $this->toArray($row['therapeutische_Orientierung'] ?? ''),
            'Sitzungen_pro_Woche_letztes_Jahr' => $this->floatOrNull($row['Sitzungen_pro_Woche_letztes_Jahr'] ?? ''),
            'first_seen_batch' => $batchId,
        ];

        $types = [
            'hat_Approbation_PsychThG' => 'boolean',
            'hat_Fachkunde' => 'boolean',
            'Fachkunde_Schwerpunkt' => Connection::PARAM_STR_ARRAY,
            'Fachkunde_Richtlinienverfahren' => Connection::PARAM_STR_ARRAY,
            'therapeutische_Orientierung' => Connection::PARAM_STR_ARRAY,
            'first_seen_batch' => 'uuid',
        ];

        $this->db->insert('therapists', $data, $types);
    }

    /** Insert one row into patients_adult. */
    private function insertPatient(array $row, Uuid $batchId): void
    {
        $data = [
            'patient_id' => $this->strOrNull($row['patient_id'] ?? ''),
            'PLZv2' => $this->intOrNull($row['PLZv2'] ?? ''),
            'Geb_Monat' => $this->intOrNull($row['Geb_Monat'] ?? ''),
            'Geb_jahr' => $this->intOrNull($row['Geb_jahr'] ?? ''),
            'Gewicht' => $this->floatOrNull($row['Gewicht'] ?? ''),
            'Groesse' => $this->intOrNull($row['Groesse'] ?? ''),
            'Sex' => $this->strOrNull($row['Sex'] ?? ''),
            'Gender' => $this->toArray($row['Gender'] ?? ''),
            'Familienstand' => $this->toArray($row['Familienstand'] ?? ''),
            'Kinder' => $this->boolOrNull($row['Kinder'] ?? ''),
            'Kinder_Anzahl' => $this->intOrNull($row['Kinder_Anzahl'] ?? ''),
            'Migration_dich' => $this->boolOrNull($row['Migration_dich'] ?? ''),
            'Mehrlingsgeburt' => $this->boolOrNull($row['Mehrlingsgeburt'] ?? ''),
            'Haushalt_anzahl' => $this->intOrNull($row['Haushalt_anzahl'] ?? ''),
            'Schulabschluss' => $this->strOrNull($row['Schulabschluss'] ?? ''),
            'Bildungsabschluss' => $this->strOrNull($row['Bildungsabschluss'] ?? ''),
            'Berufstaetig' => $this->strOrNull($row['Berufstaetig'] ?? ''),
            'Erwerbsunfaehig' => $this->strOrNull($row['Erwerbsunfaehig'] ?? ''),
            'Erwerbsunfaehig_psy' => $this->strOrNull($row['Erwerbsunfaehig_psy'] ?? ''),
            'Nettoeinkommen_jahr_hh' => $this->strOrNull($row['Nettoeinkommen_jahr_hh'] ?? ''),
            'SSS' => $this->intOrNull($row['SSS'] ?? ''),
            'Sport_aktuell' => $this->strOrNull($row['Sport_aktuell'] ?? ''),
            'MNspiel' => $this->strOrNull($row['MNspiel'] ?? ''),
            'MNsoznw' => $this->strOrNull($row['MNsoznw'] ?? ''),
            'Erstkontakt' => $this->boolOrNull($row['Erstkontakt'] ?? ''),
            'Erstkontakt_alter' => $this->intOrNull($row['Erstkontakt_alter'] ?? ''),
            'Behandlung_dich' => $this->boolOrNull($row['Behandlung_dich'] ?? ''),
            'Diagnose_selbst_dich' => $this->boolOrNull($row['Diagnose_selbst_dich'] ?? ''),
            'Diagnose_PD' => $this->boolOrNull($row['Diagnose_PD'] ?? ''),
            'Diagnose_DD' => $this->boolOrNull($row['Diagnose_DD'] ?? ''),
            'Diagnose_BD' => $this->boolOrNull($row['Diagnose_BD'] ?? ''),
            'Diagnose_AD' => $this->boolOrNull($row['Diagnose_AD'] ?? ''),
            'Diagnose_OCD' => $this->boolOrNull($row['Diagnose_OCD'] ?? ''),
            'Diagnose_PTSD' => $this->boolOrNull($row['Diagnose_PTSD'] ?? ''),
            'Diagnose_SD' => $this->boolOrNull($row['Diagnose_SD'] ?? ''),
            'Diagnose_ED' => $this->boolOrNull($row['Diagnose_ED'] ?? ''),
            'Diagnose_PBD' => $this->boolOrNull($row['Diagnose_PBD'] ?? ''),
            'Diagnose_DMD' => $this->boolOrNull($row['Diagnose_DMD'] ?? ''),
            'Diagnose_ADHD' => $this->boolOrNull($row['Diagnose_ADHD'] ?? ''),
            'Diagnose_ASD' => $this->boolOrNull($row['Diagnose_ASD'] ?? ''),
            'Diagnose_SUD' => $this->boolOrNull($row['Diagnose_SUD'] ?? ''),
            'Diagnose_D' => $this->boolOrNull($row['Diagnose_D'] ?? ''),
            'Diagnose_fam_dich' => $this->strOrNull($row['Diagnose_fam_dich'] ?? ''),
            'Suizidversuch' => $this->boolOrNull($row['Suizidversuch'] ?? ''),
            'Suizidversuche_anzahl' => $this->intOrNull($row['Suizidversuche_anzahl'] ?? ''),
            'Hilfesystem_Erfahrung' => $this->boolOrNull($row['Hilfesystem_Erfahrung'] ?? ''),
            'ACE_8' => $this->boolOrNull($row['ACE_8'] ?? ''),
            'MEHM_GZ' => $this->strOrNull($row['MEHM_GZ'] ?? ''),
            'MEHM_GALI_1' => $this->strOrNull($row['MEHM_GALI_1'] ?? ''),
            'EQ5D5L_3' => $this->strOrNull($row['EQ5D5L_3'] ?? ''),
            'vas_PSYBEL' => $this->intOrNull($row['vas_PSYBEL'] ?? ''),
            'vas_SOZEX100' => $this->intOrNull($row['vas_SOZEX100'] ?? ''),
            'vas_SOZDIS100' => $this->intOrNull($row['vas_SOZDIS100'] ?? ''),
            'vas_STIGMA100' => $this->intOrNull($row['vas_STIGMA100'] ?? ''),
            'vas_SATIS100' => $this->intOrNull($row['vas_SATIS100'] ?? ''),
            'SoC_1' => $this->strOrNull($row['SoC_1'] ?? ''),
            'LONELINESS_SIDF' => $this->strOrNull($row['LONELINESS_SIDF'] ?? ''),
            'IPSM_24' => $this->strOrNull($row['IPSM_24'] ?? ''),
            'CGI_S_sr' => $this->strOrNull($row['CGI_S_sr'] ?? ''),
            'BESCA_DM_Oa' => $this->strOrNull($row['BESCA_DM_Oa'] ?? ''),
            'PNRS_sucht_mutter' => $this->boolOrNull($row['PNRS_sucht_mutter'] ?? ''),
            'PNRS_psych_mutter' => $this->boolOrNull($row['PNRS_psych_mutter'] ?? ''),
            'PNRS_rauchen_schwangerschaft' => $this->boolOrNull($row['PNRS_rauchen_schwangerschaft'] ?? ''),
            'CTS_emotionale_misshandlung' => $this->intOrNull($row['CTS_emotionale_misshandlung'] ?? ''),
            'CTS_koerperliche_misshandlung' => $this->intOrNull($row['CTS_koerperliche_misshandlung'] ?? ''),
            'CTS_sexueller_missbrauch' => $this->intOrNull($row['CTS_sexueller_missbrauch'] ?? ''),
            'CTS_emotionale_vernachlaessigung' => $this->intOrNull($row['CTS_emotionale_vernachlaessigung'] ?? ''),
            'CTS_koerperliche_vernachlaessigung' => $this->intOrNull($row['CTS_koerperliche_vernachlaessigung'] ?? ''),
            'first_seen_batch' => $batchId,
        ];

        $types = [
            'Gender' => Connection::PARAM_STR_ARRAY,
            'Familienstand' => Connection::PARAM_STR_ARRAY,
            'Kinder' => 'boolean',
            'Migration_dich' => 'boolean',
            'Mehrlingsgeburt' => 'boolean',
            'Erstkontakt' => 'boolean',
            'Behandlung_dich' => 'boolean',
            'Diagnose_selbst_dich' => 'boolean',
            'Diagnose_PD' => 'boolean',
            'Diagnose_DD' => 'boolean',
            'Diagnose_BD' => 'boolean',
            'Diagnose_AD' => 'boolean',
            'Diagnose_OCD' => 'boolean',
            'Diagnose_PTSD' => 'boolean',
            'Diagnose_SD' => 'boolean',
            'Diagnose_ED' => 'boolean',
            'Diagnose_PBD' => 'boolean',
            'Diagnose_DMD' => 'boolean',
            'Diagnose_ADHD' => 'boolean',
            'Diagnose_ASD' => 'boolean',
            'Diagnose_SUD' => 'boolean',
            'Diagnose_D' => 'boolean',
            'Suizidversuch' => 'boolean',
            'Hilfesystem_Erfahrung' => 'boolean',
            'ACE_8' => 'boolean',
            'PNRS_sucht_mutter' => 'boolean',
            'PNRS_psych_mutter' => 'boolean',
            'PNRS_rauchen_schwangerschaft' => 'boolean',
            'first_seen_batch' => 'uuid',
        ];

        $this->db->insert('patients_adult', $data, $types);
    }

    /** Insert one row into treatments. */
    private function insertTreatment(array $row, Uuid $batchId): void
    {
        $data = [
            'patient_id' => $row['patient_id'],
            'therapist_id' => $row['therapist_id'],
            'facility_id' => $row['facility_id'],
            'treatment_id' => $this->strOrNull($row['treatment_id'] ?? ''),
            'Therapie_Setting' => $this->toArray($row['Therapie_Setting'] ?? ''),
            'Therapie_Format' => $this->toArray($row['Therapie_Format'] ?? ''),
            'Video_Sitzungen' => $this->boolOrNull($row['Video_Sitzungen'] ?? ''),
            'Sprache_der_Therapie' => $this->strOrNull($row['Sprache_der_Therapie'] ?? ''),
            'Dolmetscher_Einbezug' => $this->boolOrNull($row['Dolmetscher_Einbezug'] ?? ''),
            'Sprechstunden_vor_Behandlung' => $this->intOrNull($row['Sprechstunden_vor_Behandlung'] ?? ''),
            'Probatorische_Sitzungen' => $this->intOrNull($row['Probatorische_Sitzungen'] ?? ''),
            'Einzeltherapie_50min' => $this->intOrNull($row['Einzeltherapie_50min'] ?? ''),
            'Gruppentherapie_100min' => $this->intOrNull($row['Gruppentherapie_100min'] ?? ''),
            'Bezugspersonengespraeche' => $this->intOrNull($row['Bezugspersonengespraeche'] ?? ''),
            'Durchschnittliche_Frequenz' => $this->strOrNull($row['Durchschnittliche_Frequenz'] ?? ''),
            'Behandlungsbeginn' => $this->dateOrNull($row['Behandlungsbeginn'] ?? ''),
            'Behandlungsende_Datum' => $this->dateOrNull($row['Behandlungsende_Datum'] ?? ''),
            'Behandlungsende_Art' => $this->strOrNull($row['Behandlungsende_Art'] ?? ''),
            'TherapeutInnen_Wechsel' => $this->boolOrNull($row['TherapeutInnen_Wechsel'] ?? ''),
            'batch_id' => $batchId,
        ];

        $types = [
            'Therapie_Setting' => Connection::PARAM_STR_ARRAY,
            'Therapie_Format' => Connection::PARAM_STR_ARRAY,
            'Video_Sitzungen' => 'boolean',
            'Dolmetscher_Einbezug' => 'boolean',
            'TherapeutInnen_Wechsel' => 'boolean',
            'batch_id' => 'uuid',
        ];

        $this->db->insert('treatments', $data, $types);
    }

    /** Insert one row into assessments. */
    private function insertAssessment(array $row, Uuid $batchId): void
    {
        $data = [
            'treatment_id' => $row['treatment_id'],
            'timepoint' => $row['timepoint'] ?? null,
            'assessed_at' => $this->dateOrNull($row['assessed_at'] ?? ''),
            'P_Faktor_Item_1' => $this->strOrNull($row['P_Faktor_Item_1'] ?? ''),
            'P_Faktor_Item_2' => $this->strOrNull($row['P_Faktor_Item_2'] ?? ''),
            'P_Faktor_Item_3' => $this->strOrNull($row['P_Faktor_Item_3'] ?? ''),
            'P_Faktor_Item_4' => $this->strOrNull($row['P_Faktor_Item_4'] ?? ''),
            'P_Faktor_Item_5' => $this->strOrNull($row['P_Faktor_Item_5'] ?? ''),
            'P_Faktor_Item_6' => $this->strOrNull($row['P_Faktor_Item_6'] ?? ''),
            'P_Faktor_Item_7' => $this->strOrNull($row['P_Faktor_Item_7'] ?? ''),
            'P_Faktor_Item_8' => $this->strOrNull($row['P_Faktor_Item_8'] ?? ''),
            'Spectra_SOM' => $this->strOrNull($row['Spectra_SOM'] ?? ''),
            'Spectra_INT_Depression_Anhedonie' => $this->strOrNull($row['Spectra_INT_Depression_Anhedonie'] ?? ''),
            'Spectra_INT_Item_1' => $this->strOrNull($row['Spectra_INT_Item_1'] ?? ''),
            'Spectra_INT_Item_2' => $this->strOrNull($row['Spectra_INT_Item_2'] ?? ''),
            'Spectra_INT_Item_3' => $this->strOrNull($row['Spectra_INT_Item_3'] ?? ''),
            'Spectra_INT_Item_4' => $this->strOrNull($row['Spectra_INT_Item_4'] ?? ''),
            'Spectra_INT_Item_5' => $this->strOrNull($row['Spectra_INT_Item_5'] ?? ''),
            'Spectra_INT_Item_6' => $this->strOrNull($row['Spectra_INT_Item_6'] ?? ''),
            'Spectra_INT_Item_7' => $this->strOrNull($row['Spectra_INT_Item_7'] ?? ''),
            'Spectra_INT_Angst_Panik' => $this->strOrNull($row['Spectra_INT_Angst_Panik'] ?? ''),
            'Spectra_INT_Angst_Phobie' => $this->strOrNull($row['Spectra_INT_Angst_Phobie'] ?? ''),
            'Spectra_INT_Angst_Soziale_Phobie' => $this->strOrNull($row['Spectra_INT_Angst_Soziale_Phobie'] ?? ''),
            'Spectra_INT_Item_11' => $this->strOrNull($row['Spectra_INT_Item_11'] ?? ''),
            'Spectra_INT_Item_12' => $this->strOrNull($row['Spectra_INT_Item_12'] ?? ''),
            'Spectra_INT_Essverhalten_Appetit' => $this->strOrNull($row['Spectra_INT_Essverhalten_Appetit'] ?? ''),
            'EatingDisorder_Bulimic' => $this->strOrNull($row['EatingDisorder_Bulimic'] ?? ''),
            'EatingDisorder_Anorectic' => $this->strOrNull($row['EatingDisorder_Anorectic'] ?? ''),
            'SNAQ_3' => $this->boolOrNull($row['SNAQ_3'] ?? ''),
            'EAT26D_23' => $this->strOrNull($row['EAT26D_23'] ?? ''),
            'OCD' => $this->strOrNull($row['OCD'] ?? ''),
            'Spectra_TD_Item_1' => $this->strOrNull($row['Spectra_TD_Item_1'] ?? ''),
            'Spectra_TD_Item_2' => $this->strOrNull($row['Spectra_TD_Item_2'] ?? ''),
            'Spectra_TD_Item_3' => $this->strOrNull($row['Spectra_TD_Item_3'] ?? ''),
            'Spectra_TD_Item_4' => $this->strOrNull($row['Spectra_TD_Item_4'] ?? ''),
            'Spectra_TD_Item_5' => $this->strOrNull($row['Spectra_TD_Item_5'] ?? ''),
            'Soziale_Anhedonie' => $this->strOrNull($row['Soziale_Anhedonie'] ?? ''),
            'Spectra_DET_Item_2' => $this->strOrNull($row['Spectra_DET_Item_2'] ?? ''),
            'Spectra_DET_Item_3' => $this->strOrNull($row['Spectra_DET_Item_3'] ?? ''),
            'Spectra_DET_Item_4' => $this->strOrNull($row['Spectra_DET_Item_4'] ?? ''),
            'Spectra_DET_Item_5' => $this->strOrNull($row['Spectra_DET_Item_5'] ?? ''),
            'Spectra_EXT_Item_1' => $this->strOrNull($row['Spectra_EXT_Item_1'] ?? ''),
            'Spectra_EXT_Item_2' => $this->strOrNull($row['Spectra_EXT_Item_2'] ?? ''),
            'Spectra_EXT_Item_3' => $this->strOrNull($row['Spectra_EXT_Item_3'] ?? ''),
            'Spectra_EXT_Item_4' => $this->strOrNull($row['Spectra_EXT_Item_4'] ?? ''),
            'Spectra_EXT_Item_5' => $this->strOrNull($row['Spectra_EXT_Item_5'] ?? ''),
            'Spectra_EXT_Item_6' => $this->strOrNull($row['Spectra_EXT_Item_6'] ?? ''),
            'Spectra_EXT_Item_7' => $this->strOrNull($row['Spectra_EXT_Item_7'] ?? ''),
            'Spectra_EXT_Item_8' => $this->strOrNull($row['Spectra_EXT_Item_8'] ?? ''),
            'Aggressivitaet' => $this->strOrNull($row['Aggressivitaet'] ?? ''),
            'Spectra_EXT_Item_10' => $this->strOrNull($row['Spectra_EXT_Item_10'] ?? ''),
            'Spectra_EXT_Item_11' => $this->strOrNull($row['Spectra_EXT_Item_11'] ?? ''),
            'Spectra_EXT_Item_12' => $this->strOrNull($row['Spectra_EXT_Item_12'] ?? ''),
            'Spectra_EXT_Item_13' => $this->strOrNull($row['Spectra_EXT_Item_13'] ?? ''),
            'globales_Wohlbefinden_VAS' => $this->intOrNull($row['globales_Wohlbefinden_VAS'] ?? ''),
            'batch_id' => $batchId,
        ];

        $types = [
            'SNAQ_3' => 'boolean',
            'batch_id' => 'uuid',
        ];

        $this->db->insert('assessments', $data, $types);
    }

    /** Insert one row into process_ratings. */
    private function insertProcessRating(array $row, Uuid $batchId): void
    {
        $data = [
            'treatment_id' => $row['treatment_id'],
            'Alliance_Bond_Capacity_1' => $this->strOrNull($row['Alliance_Bond_Capacity_1'] ?? ''),
            'Alliance_Bond_Capacity_2' => $this->strOrNull($row['Alliance_Bond_Capacity_2'] ?? ''),
            'Alliance_Bond_Capacity_3' => $this->strOrNull($row['Alliance_Bond_Capacity_3'] ?? ''),
            'Alliance_Bond_Capacity_4' => $this->strOrNull($row['Alliance_Bond_Capacity_4'] ?? ''),
            'Persuasiveness_Credibility_1' => $this->strOrNull($row['Persuasiveness_Credibility_1'] ?? ''),
            'Persuasiveness_Credibility_2' => $this->strOrNull($row['Persuasiveness_Credibility_2'] ?? ''),
            'Goal_Consensus_1' => $this->strOrNull($row['Goal_Consensus_1'] ?? ''),
            'Goal_Consensus_2' => $this->strOrNull($row['Goal_Consensus_2'] ?? ''),
            'Goal_Consensus_3' => $this->strOrNull($row['Goal_Consensus_3'] ?? ''),
            'Rupture_Repair_1' => $this->strOrNull($row['Rupture_Repair_1'] ?? ''),
            'Rupture_Repair_2' => $this->strOrNull($row['Rupture_Repair_2'] ?? ''),
            'Rupture_Repair_3' => $this->strOrNull($row['Rupture_Repair_3'] ?? ''),
            'Ressource_Activation_1' => $this->strOrNull($row['Ressource_Activation_1'] ?? ''),
            'Ressource_Activation_2' => $this->strOrNull($row['Ressource_Activation_2'] ?? ''),
            'Ressource_Activation_3' => $this->strOrNull($row['Ressource_Activation_3'] ?? ''),
            'Congruence_1' => $this->strOrNull($row['Congruence_1'] ?? ''),
            'Congruence_2' => $this->strOrNull($row['Congruence_2'] ?? ''),
            'Managing_Countertransference_1' => $this->strOrNull($row['Managing_Countertransference_1'] ?? ''),
            'Managing_Countertransference_2' => $this->strOrNull($row['Managing_Countertransference_2'] ?? ''),
            'CBT_1' => $this->strOrNull($row['CBT_1'] ?? ''),
            'CBT_2' => $this->strOrNull($row['CBT_2'] ?? ''),
            'CBT_3' => $this->strOrNull($row['CBT_3'] ?? ''),
            'DYN_1' => $this->strOrNull($row['DYN_1'] ?? ''),
            'DYN_2' => $this->strOrNull($row['DYN_2'] ?? ''),
            'DYN_3' => $this->strOrNull($row['DYN_3'] ?? ''),
            'timepoint' => $row['timepoint'] ?? null,
            'batch_id' => $batchId,
        ];

        $types = [
            'batch_id' => 'uuid',
        ];

        $this->db->insert('process_ratings', $data, $types);
    }


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

}
