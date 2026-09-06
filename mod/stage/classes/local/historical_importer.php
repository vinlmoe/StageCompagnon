<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_stage\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Lecteur du classeur historique « Suivi des STAGES et EP ».
 *
 * Le format est volontairement reconnu par les intitulés des feuilles et des colonnes plutôt
 * que par leurs positions : l'ajout demandé d'une colonne Email ne doit pas casser les blocs.
 *
 * @package mod_stage
 */
class historical_importer {

    /** @var string Nom de la feuille des stages obligatoires. */
    const MANDATORY_SHEET = 'stages validation er';

    /** @var string Variante utilisée par le classeur de suivi 2025-26. */
    const MANDATORY_SHEET_SINGULAR = 'stage validation er';

    /** @var string Nom de la feuille des stages EP. */
    const COMPLEMENTARY_SHEET = 'stage ep validation er';

    /**
     * Lit les deux feuilles de stages du classeur.
     *
     * @param string $filepath
     * @return array{records: array, warnings: array}
     */
    public static function read(string $filepath): array {
        $allsheets = self::read_sheets($filepath);
        $result = ['records' => [], 'warnings' => []];
        foreach ($allsheets as $sheetname => $rows) {
            $normalized = self::normalize($sheetname);
            $mandatory = in_array($normalized,
                [self::MANDATORY_SHEET, self::MANDATORY_SHEET_SINGULAR], true);
            if (!$mandatory && $normalized !== self::COMPLEMENTARY_SHEET) {
                continue;
            }
            $parsed = self::parse_rows($rows, $normalized === self::COMPLEMENTARY_SHEET, $sheetname);
            $result['records'] = array_merge($result['records'], $parsed['records']);
            $result['warnings'] = array_merge($result['warnings'], $parsed['warnings']);
        }
        if (empty($result['records']) && empty($result['warnings'])) {
            throw new \moodle_exception('historicalimportnosheets', 'mod_stage');
        }
        return $result;
    }

    /**
     * Lit toutes les feuilles d'un classeur XLSX sans interpréter leur contenu.
     *
     * Ce lecteur léger est aussi utilisé pour réimporter l'export global du module.
     *
     * @param string $filepath
     * @return array<string, array>
     */
    public static function read_sheets(string $filepath): array {
        if (!class_exists('ZipArchive')) {
            throw new \moodle_exception('historicalimportnozip', 'mod_stage');
        }

        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new \moodle_exception('historicalimportinvalidfile', 'mod_stage');
        }

        try {
            $sharedstrings = self::read_shared_strings($zip);
            $sheets = self::read_workbook_sheets($zip);
            $result = [];
            foreach ($sheets as $sheetname => $sheetpath) {
                $result[$sheetname] = self::read_sheet($zip, $sheetpath, $sharedstrings);
            }
            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Transforme une matrice de feuille en une ligne par stage validé.
     *
     * @param array $rows
     * @param bool $complementary
     * @param string $sheetname
     * @return array
     */
    public static function parse_rows(array $rows, bool $complementary, string $sheetname): array {
        $records = [];
        $warnings = [];
        $headers = $rows[1] ?? [];
        $emailcol = self::find_header($headers, ['email', 'e mail', 'courriel']);
        if ($emailcol === null) {
            return ['records' => [], 'warnings' => [get_string('historicalimportmissingemail', 'mod_stage', $sheetname)]];
        }

        $lastnamecol = self::find_header($headers, ['nom']);
        $firstnamecol = self::find_header($headers, ['prenom']);
        $namecolumns = self::find_headers($headers, ['nom']);
        $firstnamecolumns = self::find_headers($headers, ['prenom']);
        $teacherlastnamecol = $namecolumns[1] ?? null;
        $teacherfirstnamecol = $firstnamecolumns[1] ?? null;
        $blockstarts = [];
        foreach ($headers as $col => $header) {
            $value = self::normalize((string) $header);
            if ($value === 'lieu et date' || $value === 'lieu du stage'
                    || $value === 'convention enregistree') {
                $blockstarts[] = $col;
            }
        }
        foreach ($rows as $rowindex => $row) {
            if ($rowindex < 2) {
                continue;
            }
            $email = trim((string) ($row[$emailcol] ?? ''));
            $studentname = trim((string) ($row[$firstnamecol] ?? '') . ' ' . (string) ($row[$lastnamecol] ?? ''));
            $teachername = trim((string) ($row[$teacherfirstnamecol] ?? '') . ' '
                . (string) ($row[$teacherlastnamecol] ?? ''));
            if ($email === '') {
                if ($studentname !== '' && self::row_has_stage_data($row, $blockstarts)) {
                    $warnings[] = get_string('historicalimportrownoemail', 'mod_stage', (object) [
                        'sheet' => $sheetname, 'line' => $rowindex + 1, 'student' => $studentname,
                    ]);
                }
                continue;
            }
            foreach ($blockstarts as $blockindex => $start) {
                $end = $blockstarts[$blockindex + 1]
                    ?? (empty($headers) ? $start + 1 : max(array_keys($headers)) + 1);
                $columns = self::block_columns($headers, $start, $end);
                $validated = self::is_validated($row[$columns['validation']] ?? '');
                $rawlocation = trim((string) ($row[$columns['location']] ?? ''));
                $duration = (int) round((float) str_replace(',', '.', (string) ($row[$columns['duration']] ?? 0)));
                $studyyear = (int) ($row[$columns['year']] ?? 0);
                if (!$validated || ($rawlocation === '' && $duration === 0 && $studyyear === 0)) {
                    continue;
                }

                $themename = $complementary ? 'EP Stages' : self::theme_name($rows[0][$start] ?? '');
                if ($themename === '') {
                    $warnings[] = get_string('historicalimportnotheme', 'mod_stage', (object) [
                        'sheet' => $sheetname, 'line' => $rowindex + 1,
                    ]);
                    continue;
                }
                [$datestart, $dateend] = self::parse_dates($rawlocation);
                if (!$datestart || !$dateend) {
                    $warnings[] = get_string('historicalimportdateswarning', 'mod_stage', (object) [
                        'sheet' => $sheetname, 'line' => $rowindex + 1, 'value' => $rawlocation ?: '-',
                    ]);
                }
                $records[] = (object) [
                    'email' => $email,
                    'studentname' => $studentname,
                    'themename' => $themename,
                    'structure' => \core_text::substr($rawlocation, 0, 255),
                    'datestart' => $datestart,
                    'dateend' => $dateend,
                    'duration' => max(0, $duration),
                    'studyyear' => ($studyyear >= 1 && $studyyear <= 6) ? $studyyear : 0,
                    'stagetype' => $complementary ? 'complementaire' : 'obligatoire',
                    'teachername' => $teachername,
                    'source' => $sheetname . ' — ligne ' . ($rowindex + 1),
                ];
            }
        }
        return ['records' => $records, 'warnings' => $warnings];
    }

    /** @return bool */
    private static function row_has_stage_data(array $row, array $starts): bool {
        foreach ($starts as $start) {
            if (trim((string) ($row[$start] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** @return array */
    private static function block_columns(array $headers, int $start, int $end): array {
        $found = ['location' => $start, 'validation' => $start + 1, 'duration' => $start + 2, 'year' => $end - 1];
        for ($col = $start; $col < $end; $col++) {
            $header = self::normalize((string) ($headers[$col] ?? ''));
            if (strpos($header, 'valid') !== false) {
                $found['validation'] = $col;
            } else if (strpos($header, 'jour') !== false || $header === 'duree en jours') {
                $found['duration'] = $col;
            } else if (strpos($header, 'annee') !== false) {
                $found['year'] = $col;
            }
        }
        return $found;
    }

    /** @return int|null */
    private static function find_header(array $headers, array $names): ?int {
        foreach ($headers as $col => $header) {
            if (in_array(self::normalize((string) $header), $names, true)) {
                return $col;
            }
        }
        return null;
    }

    /** @return array */
    private static function find_headers(array $headers, array $names): array {
        $columns = [];
        foreach ($headers as $col => $header) {
            if (in_array(self::normalize((string) $header), $names, true)) {
                $columns[] = $col;
            }
        }
        return $columns;
    }

    /** @return bool */
    private static function is_validated($value): bool {
        return in_array(self::normalize((string) $value), ['1', 'v', 'valide', 'oui', 'true'], true);
    }

    /** @return string */
    private static function theme_name($value): string {
        $line = preg_split('/[\r\n]+/', trim((string) $value))[0] ?? '';
        return trim($line);
    }

    /**
     * Extraction prudente des formats les plus fréquents. En cas d'ambiguïté, aucune date n'est
     * inventée : le texte original reste dans la structure et la prévisualisation le signale.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private static function parse_dates(string $text): array {
        $text = \core_text::strtolower(trim(str_replace(['–', '—'], '-', $text)));
        preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.](\d{2,4}))?\b/', $text, $matches,
            PREG_SET_ORDER);
        if (count($matches) === 1
                && preg_match('/\b(\d{1,2})\s+(?:au|a)\s+' . preg_quote($matches[0][0], '/') . '/u',
                    $text, $shortstart)) {
            array_unshift($matches, [$shortstart[1] . '/' . $matches[0][2], $shortstart[1], $matches[0][2],
                $matches[0][3] ?? '']);
        }
        if (count($matches) < 2) {
            return [null, null];
        }
        $first = $matches[0];
        $last = $matches[count($matches) - 1];
        $year = self::full_year($last[3] ?? '');
        $firstyear = self::full_year($first[3] ?? '') ?: $year;
        if (!$year || !$firstyear) {
            return [null, null];
        }
        $start = checkdate((int) $first[2], (int) $first[1], $firstyear)
            ? make_timestamp($firstyear, (int) $first[2], (int) $first[1]) : null;
        $end = checkdate((int) $last[2], (int) $last[1], $year)
            ? make_timestamp($year, (int) $last[2], (int) $last[1]) : null;
        if ($start && $end && $start > $end && empty($first[3])) {
            $firstyear--;
            $start = checkdate((int) $first[2], (int) $first[1], $firstyear)
                ? make_timestamp($firstyear, (int) $first[2], (int) $first[1]) : null;
        }
        return ($start && $end && $end >= $start) ? [$start, $end] : [null, null];
    }

    /** @return int */
    private static function full_year(string $year): int {
        if ($year === '') {
            return 0;
        }
        $value = (int) $year;
        return $value < 100 ? 2000 + $value : $value;
    }

    /** @return string */
    private static function normalize(string $value): string {
        $value = \core_text::strtolower(trim($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /** @return array */
    private static function read_shared_strings(\ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = self::xml($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($xpath->query('//x:si') as $item) {
            $value = '';
            foreach ($xpath->query('.//x:t', $item) as $text) {
                $value .= $text->textContent;
            }
            $strings[] = $value;
        }
        return $strings;
    }

    /** @return array */
    private static function read_workbook_sheets(\ZipArchive $zip): array {
        $workbook = self::xml($zip->getFromName('xl/workbook.xml'));
        $relations = self::xml($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relmap = [];
        foreach ($relations->documentElement->childNodes as $relation) {
            if ($relation instanceof \DOMElement) {
                $relmap[$relation->getAttribute('Id')] = $relation->getAttribute('Target');
            }
        }
        $xpath = new \DOMXPath($workbook);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = [];
        foreach ($xpath->query('//x:sheet') as $sheet) {
            $target = $relmap[$sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')] ?? '';
            if ($target !== '') {
                $target = ltrim(preg_replace('#^\.\./#', '', $target), '/');
                $sheets[$sheet->getAttribute('name')] = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
            }
        }
        return $sheets;
    }

    /** @return array */
    private static function read_sheet(\ZipArchive $zip, string $path, array $sharedstrings): array {
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return [];
        }
        $doc = self::xml($xml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//x:sheetData/x:row') as $row) {
            $rowindex = (int) $row->getAttribute('r') - 1;
            foreach ($xpath->query('./x:c', $row) as $cell) {
                preg_match('/^[A-Z]+/', $cell->getAttribute('r'), $columnmatch);
                $col = self::column_index($columnmatch[0] ?? 'A');
                $value = '';
                $valuenode = $xpath->query('./x:v', $cell)->item(0);
                if ($cell->getAttribute('t') === 'inlineStr') {
                    foreach ($xpath->query('.//x:t', $cell) as $textnode) {
                        $value .= $textnode->textContent;
                    }
                } else if ($valuenode) {
                    $value = $valuenode->textContent;
                    if ($cell->getAttribute('t') === 's') {
                        $value = $sharedstrings[(int) $value] ?? '';
                    }
                }
                $rows[$rowindex][$col] = $value;
            }
        }
        return $rows;
    }

    /** @return int */
    private static function column_index(string $letters): int {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }
        return $index - 1;
    }

    /** @return \DOMDocument */
    private static function xml(string $content): \DOMDocument {
        $doc = new \DOMDocument();
        if (!$doc->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new \moodle_exception('historicalimportinvalidfile', 'mod_stage');
        }
        return $doc;
    }
}
