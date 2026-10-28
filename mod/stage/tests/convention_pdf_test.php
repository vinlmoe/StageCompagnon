<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->libdir . '/pdflib.php');

/**
 * Génération du PDF de la convention : les dates sont écrites dans la langue du gabarit choisi,
 * pas dans celle de la DEVE qui génère la convention, et la génération ne produit aucun message
 * de débogage (qui, écrit avant le PDF, en empêcherait l'envoi).
 *
 * L'environnement de test n'a que l'anglais : un paquet de langue français minimal est créé dans
 * le dossier de données, avec la locale et le format de date nécessaires.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_convention_date
 * @covers     ::stage_build_convention_pdf
 */
final class convention_pdf_test extends \advanced_testcase {
    /**
     * Installe un paquet de langue français minimal et vide les caches de chaînes.
     *
     * @return void
     */
    private function install_french(): void {
        global $CFG;

        $dir = $CFG->dataroot . '/lang/fr';
        check_dir_exists($dir);
        file_put_contents($dir . '/langconfig.php', "<?php\n"
            . "\$string['thislanguage'] = 'Français';\n"
            . "\$string['thislanguageint'] = 'French';\n"
            . "\$string['parentlanguage'] = '';\n"
            . "\$string['locale'] = 'fr_FR.UTF-8';\n"
            . "\$string['strftimedate'] = '%d %B %Y';\n");
        get_string_manager()->reset_caches();
    }

    /**
     * Activité, étudiant, stage daté du 16 novembre au 4 décembre 2026, et deux gabarits de
     * convention (français et anglais) portant chacun un vrai fichier PDF.
     *
     * @return array Activité, contexte, stage, gabarits indexés par langue.
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Camille', 'lastname' => 'Leroy']);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $entry = $generator->create_entry($stage, $student->id, $generator->create_theme($stage), [
            'datestart' => make_timestamp(2026, 11, 16, 12),
            'dateend' => make_timestamp(2026, 12, 4, 12),
        ]);

        $templates = [];
        foreach (['fr', 'en'] as $lang) {
            $templates[$lang] = $this->create_template($stage->id, $context, $lang);
        }
        return [$stage, $context, $entry, $templates];
    }

    /**
     * Gabarit de convention d'une langue, avec un PDF d'une page en guise d'articles.
     *
     * @param int $stageid
     * @param \context_module $context
     * @param string $lang
     * @return int Identifiant du gabarit.
     */
    private function create_template(int $stageid, \context_module $context, string $lang): int {
        global $DB;

        $templateid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $stageid, 'name' => 'Gabarit ' . $lang, 'lang' => $lang,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $pdf = new \pdf();
        $pdf->AddPage();
        $pdf->Write(10, 'Articles');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => 'conventiontemplate',
            'itemid' => $templateid, 'filepath' => '/', 'filename' => 'articles.pdf',
        ], $pdf->Output('', 'S'));
        return $templateid;
    }

    /**
     * Texte du PDF : ses flux sont décompressés, et les chaînes UTF-16 des polices Unicode de
     * TCPDF ramenées en UTF-8, pour pouvoir y chercher une date.
     *
     * @param string $pdf Contenu du fichier PDF.
     * @return string
     */
    private function pdf_text(string $pdf): string {
        $text = '';
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        foreach ($streams[1] as $stream) {
            $content = @zlib_decode($stream);
            $text .= ($content === false ? $stream : $content) . "\n";
        }
        // Chaînes littérales des opérateurs de texte (« (...) Tj » ou « [(...)] TJ »), encodées en
        // UTF-16BE par TCPDF.
        $decoded = '';
        preg_match_all('/\((.*?)(?<!\\\\)\)\]?\s*T[jJ]/s', $text, $strings);
        foreach ($strings[1] as $string) {
            $string = stripcslashes($string);
            $decoded .= \core_text::convert($string, 'UTF-16BE', 'UTF-8') . "\n";
        }
        return $decoded;
    }

    /**
     * La date est écrite dans la langue demandée, et la langue de la session est rétablie.
     */
    public function test_convention_date_uses_the_requested_language(): void {
        $this->resetAfterTest();
        $this->install_french();
        $time = make_timestamp(2026, 11, 16, 12);

        force_current_language('en');
        $this->assertSame('16 novembre 2026', stage_convention_date($time, 'fr'));
        $this->assertSame('en', current_language());

        force_current_language('fr');
        $this->assertSame('16 November 2026', stage_convention_date($time, 'en'));
        $this->assertSame('fr', current_language());
        force_current_language('');
    }

    /**
     * Une convention au gabarit anglais, générée par une DEVE en français, porte des dates en
     * anglais ; au gabarit français, des dates en français. Aucun message de débogage.
     */
    public function test_convention_pdf_dates_follow_the_template_language(): void {
        global $DB;

        [$stage, $context, $entry, $templates] = $this->prepare();
        $this->install_french();

        $expected = ['en' => ['16 November 2026', '04 December 2026'], 'fr' => ['16 novembre 2026', '04 décembre 2026']];
        $other = ['en' => 'novembre', 'fr' => 'November'];
        foreach ($templates as $lang => $templateid) {
            $DB->set_field('stage_entry', 'conventiontemplateid', $templateid, ['id' => $entry->id]);
            $entry = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);

            force_current_language('fr');
            $result = stage_build_convention_pdf($stage, $entry, $context);
            $this->assertDebuggingNotCalled();
            $this->assertSame('fr', current_language(), 'La langue de la session est rétablie.');
            force_current_language('');

            $this->assertNull($result['error']);
            $text = $this->pdf_text($result['pdf']->Output('', 'S'));
            foreach ($expected[$lang] as $date) {
                $this->assertStringContainsString(ltrim($date, '0'), $text, "Gabarit $lang : date attendue « $date ».");
            }
            $this->assertStringNotContainsString($other[$lang], $text, "Gabarit $lang : aucun mois dans l'autre langue.");
        }
    }
}
