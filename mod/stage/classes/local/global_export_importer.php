<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_stage\local;

defined('MOODLE_INTERNAL') || die();

/** Transforme la feuille « Stages » de l'export global en données restaurables. */
class global_export_importer {

    /**
     * @param string $filepath
     * @return array{records: array, warnings: array}
     */
    public static function read(string $filepath): array {
        $sheets = historical_importer::read_sheets($filepath);
        foreach ($sheets as $rows) {
            $headers = $rows[0] ?? [];
            $columns = self::columns($headers);
            if (!isset($columns['entryid'], $columns['email'], $columns['theme'])) {
                continue;
            }
            $records = [];
            foreach ($rows as $index => $row) {
                if ($index === 0 || trim(self::value($row, $columns, 'email')) === '') {
                    continue;
                }
                $record = new \stdClass();
                foreach ($columns as $field => $column) {
                    $record->$field = trim((string) ($row[$column] ?? ''));
                }
                $record->line = $index + 1;
                foreach (['datestart', 'dateend', 'studentbirthdate', 'teachertime', 'tutortime', 'devetime',
                        'canceltime', 'timecreated', 'timemodified', 'conventionrequesttime',
                        'conventionteachervalidatetime', 'conventionedittime', 'conventionsigntime',
                        'conventionrejecttime'] as $field) {
                    $record->$field = self::date($record->$field ?? '');
                }
                $records[] = $record;
            }
            return ['records' => $records, 'warnings' => []];
        }
        throw new \moodle_exception('globalimportinvalid', 'mod_stage');
    }

    /** Colonnes reconnues dans un export français ou anglais. */
    private static function columns(array $headers): array {
        $languagekeys = [
            'entryid' => 'exportentryid', 'email' => 'email', 'studyyear' => 'studyyear', 'theme' => 'theme',
            'stagetype' => 'conventionstagetype', 'structure' => 'structure', 'abroad' => 'abroad',
            'country' => 'country', 'datestart' => 'datestart', 'dateend' => 'dateend', 'periods' => 'periods',
            'declaredduration' => 'declaredduration', 'retainedduration' => 'retainedduration', 'status' => 'status',
            'conventionstatus' => 'conventionstatus', 'conventiontemplatename' => 'conventiontemplatename',
            'yearsituation' => 'conventionyearsituation', 'studentselfeval' => 'studentselfeval',
            'evaluatedby' => 'evaluatedby', 'teachertime' => 'teachervalidationtime', 'teachereval' => 'teachereval',
            'tutoreval' => 'tutorevalheading', 'tutortime' => 'tutorevaltime',
            'tutorbypassed' => 'tutorevalbypassedcolumn', 'devevalidatedby' => 'devevalidatedby',
            'devetime' => 'devevalidationtime', 'devecomment' => 'devecomment', 'cancelledby' => 'cancelledby',
            'canceltime' => 'canceltime', 'cancelcomment' => 'cancelcomment', 'timecreated' => 'timecreated',
            'timemodified' => 'lastmodified', 'referentteacher' => 'conventionreferentteacher',
            'conventionrequesttime' => 'conventionrequesttime',
            'conventionteachervalidatedby' => 'conventionteachervalidatedby',
            'conventionteachervalidatetime' => 'conventionteachervalidatetime',
            'conventioneditedby' => 'conventioneditedby', 'conventionedittime' => 'conventionedittime',
            'conventionsignedby' => 'conventionsignedby', 'conventionsigntime' => 'conventionsigntime',
            'conventionrejectedby' => 'conventionrejectedby', 'conventionrejecttime' => 'conventionrejecttime',
            'conventionrejectcomment' => 'conventionrejectcomment', 'studentbirthdate' => 'conventionbirthdate',
            'studentaddress' => 'conventionstudentaddress', 'studentphone' => 'conventionstudentphone',
            'hostaddress' => 'conventionhostaddress', 'hostrepresentative' => 'conventionhostrepresentative',
            'hostrepresentativetitle' => 'conventionhostrepresentativetitle', 'hostservice' => 'conventionhostservice',
            'hostphone' => 'conventionhostphone', 'hostemail' => 'conventionhostemail',
            'hostlocation' => 'conventionhostlocation', 'tutorname' => 'conventiontutorname',
            'tutorfunction' => 'conventiontutorfunction', 'tutorphone' => 'conventiontutorphone',
            'tutoremail' => 'conventiontutoremail', 'nightpresence' => 'conventionnightpresence',
            'sundaypresence' => 'conventionsundaypresence', 'holidaypresence' => 'conventionholidaypresence',
            'homebased' => 'conventionhomebased', 'othermodality' => 'conventionothermodality',
            'gratificationamount' => 'conventiongratification', 'hasleave' => 'conventionhasleave',
            'leavedays' => 'conventionleavedays', 'leavemodalities' => 'conventionleavemodalities',
        ];
        $aliases = [
            'entryid' => ['n de stage', 'internship id'], 'email' => ['adresse de courriel', 'email'],
            'studyyear' => ['annee d etude', 'study year'], 'theme' => ['thematique', 'theme'],
            'stagetype' => ['type de stage', 'internship type'], 'structure' => ['structure d accueil', 'host organisation'],
            'abroad' => ['a l etranger', 'abroad'], 'country' => ['pays', 'country'],
            'datestart' => ['date de debut', 'start date'], 'dateend' => ['date de fin', 'end date'],
            'periods' => ['plages de dates', 'date ranges'],
            'declaredduration' => ['duree declaree jours', 'declared duration days'],
            'retainedduration' => ['duree retenue jours', 'retained duration days'],
            'status' => ['statut', 'status'], 'conventionstatus' => ['statut de la convention', 'agreement status'],
            'conventiontemplatename' => ['gabarit de convention', 'agreement template'],
            'yearsituation' => ['situation dans l annee', 'year situation'],
            'studentselfeval' => ['auto evaluation etudiant', 'student self evaluation'],
            'evaluatedby' => ['evalue par', 'evaluated by'], 'teachertime' => ['date de validation enseignant', 'teacher validation date'],
            'teachereval' => ['evaluation enseignant', 'teacher evaluation'],
            'tutoreval' => ['evaluation du maitre de stage', 'workplace tutor evaluation'],
            'tutortime' => ['date de l evaluation du maitre de stage', 'workplace tutor evaluation date'],
            'tutorbypassed' => ['evaluation du maitre de stage ignoree', 'workplace tutor evaluation skipped'],
            'devevalidatedby' => ['valide par la deve', 'validated by the administration office'],
            'devetime' => ['date de validation deve', 'administration office validation date'],
            'devecomment' => ['commentaire deve', 'administration office comment'],
            'cancelledby' => ['annule par', 'cancelled by'], 'canceltime' => ['date d annulation', 'cancellation date'],
            'cancelcomment' => ['motif d annulation', 'cancellation comment'],
            'timecreated' => ['date de creation', 'time created'], 'timemodified' => ['derniere modification', 'last modified'],
            'referentteacher' => ['enseignant referent de la convention', 'agreement referring teacher'],
            'conventionrequesttime' => ['date de demande de convention', 'agreement request date'],
            'conventionteachervalidatedby' => ['demande de convention validee par', 'agreement request validated by'],
            'conventionteachervalidatetime' => ['date de validation de la demande', 'agreement request validation date'],
            'conventioneditedby' => ['convention editee par', 'agreement edited by'],
            'conventionedittime' => ['date d edition de la convention', 'agreement editing date'],
            'conventionsignedby' => ['convention signee par', 'agreement signed by'],
            'conventionsigntime' => ['date de signature de la convention', 'agreement signing date'],
            'conventionrejectedby' => ['convention refusee par', 'agreement rejected by'],
            'conventionrejecttime' => ['date de refus de la convention', 'agreement rejection date'],
            'conventionrejectcomment' => ['motif du refus de la convention', 'agreement rejection comment'],
            'studentbirthdate' => ['date de naissance', 'birth date'], 'studentaddress' => ['adresse de l etudiant', 'student address'],
            'studentphone' => ['telephone de l etudiant', 'student phone'], 'hostaddress' => ['adresse de l organisme', 'host address'],
            'hostrepresentative' => ['representant de l organisme', 'host representative'],
            'hostrepresentativetitle' => ['qualite du representant', 'host representative title'],
            'hostservice' => ['service d accueil', 'host service'], 'hostphone' => ['telephone de l organisme', 'host phone'],
            'hostemail' => ['courriel de l organisme', 'host email'], 'hostlocation' => ['lieu du stage', 'internship location'],
            'tutorname' => ['maitre de stage', 'workplace tutor'], 'tutorfunction' => ['fonction du maitre de stage', 'workplace tutor function'],
            'tutorphone' => ['telephone du maitre de stage', 'workplace tutor phone'],
            'tutoremail' => ['courriel du maitre de stage', 'workplace tutor email'],
            'nightpresence' => ['presence de nuit', 'night presence'], 'sundaypresence' => ['presence le dimanche', 'sunday presence'],
            'holidaypresence' => ['presence un jour ferie', 'public holiday presence'], 'homebased' => ['stage au domicile', 'home based'],
            'othermodality' => ['autre modalite', 'other arrangement'], 'gratificationamount' => ['gratification', 'allowance'],
            'hasleave' => ['conges prevus', 'leave provided'], 'leavedays' => ['nombre de jours de conge', 'leave days'],
            'leavemodalities' => ['modalites des conges', 'leave arrangements'],
        ];
        foreach ($languagekeys as $field => $key) {
            $component = $key === 'email' || $key === 'lastmodified' ? 'moodle' : 'mod_stage';
            $aliases[$field][] = self::normalize(get_string($key, $component));
        }
        $result = [];
        foreach ($headers as $column => $header) {
            $normalized = self::normalize((string) $header);
            foreach ($aliases as $field => $names) {
                if (in_array($normalized, $names, true)) {
                    $result[$field] = $column;
                }
            }
        }
        return $result;
    }

    private static function value(array $row, array $columns, string $field): string {
        return isset($columns[$field]) ? (string) ($row[$columns[$field]] ?? '') : '';
    }

    /** Convertit une date Excel ou une date textuelle issue du même export. */
    private static function date($value): ?int {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) round(((float) $value - 25569) * 86400);
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : $timestamp;
    }

    public static function normalize(string $value): string {
        $value = \core_text::strtolower(trim($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii === false ? $value : $ascii;
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $value)));
    }
}
