<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Webservice-Funktionen des Plugins block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Webservice-Funktionen des Fragebank-Filters.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_questionfilter_external extends external_api {
    /**
     * Beschreibt die Parameter der Suchfunktion.
     *
     * @return external_function_parameters Parameterdefinition.
     */
    public static function search_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Suchbegriff', VALUE_DEFAULT, ''),
            'categories' => new external_value(PARAM_TEXT, 'Kommagetrennte Kategorie-IDs (leer = alle)', VALUE_DEFAULT, ''),
            'types' => new external_value(PARAM_TEXT, 'Kommagetrennte Fragetypen', VALUE_DEFAULT, ''),
            'difficulty' => new external_value(PARAM_TEXT, 'Schwierigkeitsstufe (Tag)', VALUE_DEFAULT, ''),
            'tags' => new external_value(PARAM_TEXT, 'Kommagetrennte Tags', VALUE_DEFAULT, ''),
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Aktueller Kontext', VALUE_DEFAULT, 1),
            'limit' => new external_value(PARAM_INT, 'Max. Ergebnisse', VALUE_DEFAULT, 200),
        ]);
    }

    /**
     * Sucht Fragen anhand der uebergebenen Filterkriterien.
     *
     * @param string $search Freitext-Suchbegriff.
     * @param string $categories Kommagetrennte Kategorie-IDs, leer bedeutet alle.
     * @param string $types Kommagetrennte Fragetypen.
     * @param string $difficulty Schwierigkeitsstufe als Tag.
     * @param string $tags Kommagetrennte Tags.
     * @param string $scope Suchbereich: all, course oder system.
     * @param int $contextid ID des aktuellen Kontexts.
     * @param int $limit Maximale Anzahl an Ergebnissen.
     * @return array Liste der gefundenen Fragen.
     */
    public static function search_questions(
        string $search,
        string $categories,
        string $types,
        string $difficulty,
        string $tags,
        string $scope,
        int $contextid,
        int $limit
    ): array {
        global $DB;

        $params = self::validate_parameters(self::search_questions_parameters(), [
            'search' => $search,
            'categories' => $categories,
            'types' => $types,
            'difficulty' => $difficulty,
            'tags' => $tags,
            'scope' => $scope,
            'contextid' => $contextid,
            'limit' => $limit,
        ]);

        // Limit auf konfigurierten Maximalwert deckeln (DoS-Schutz).
        $maxlimit = (int)(get_config('block_questionfilter', 'resultlimit') ?: 200);
        if ($params['limit'] < 1 || $params['limit'] > $maxlimit) {
            $params['limit'] = $maxlimit;
        }

        // Berechtigungsprüfung — eigene Capability, Gäste können erlaubt werden.
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        // Kontexte sammeln je nach Suchbereich.
        $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);

        if (empty($contextids)) {
            return ['questions' => [], 'total' => 0];
        }

        [$ctxsql, $ctxargs] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        // Basis-Query über question + question_categories.
        $sql = "SELECT DISTINCT q.id, q.name, q.qtype, q.createdby,
                       qc.id AS categoryid, qc.name AS categoryname,
                       qc.contextid AS categorycontextid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE q.parent = 0
                   AND qv.status = 'ready'
                   AND qc.contextid $ctxsql";

        $queryargs = $ctxargs;

        // Freitext-Suche.
        if (!empty($params['search'])) {
            $sql .= " AND " . $DB->sql_like('q.name', ':search', false);
            $queryargs['search'] = '%' . $DB->sql_like_escape($params['search']) . '%';
        }

        // Fragetyp-Filter.
        if (!empty($params['types'])) {
            $typelist = array_filter(array_map('trim', explode(',', $params['types'])));
            if ($typelist) {
                [$typesql, $typeargs] = $DB->get_in_or_equal($typelist, SQL_PARAMS_NAMED, 'type');
                $sql .= " AND q.qtype $typesql";
                $queryargs = array_merge($queryargs, $typeargs);
            }
        }

        // Kategorie-Filter.
        if (!empty($params['categories'])) {
            $catids = array_filter(array_map('intval', explode(',', $params['categories'])));
            if ($catids) {
                [$catsql, $catargs] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
                $sql .= " AND qbe.questioncategoryid $catsql";
                $queryargs = array_merge($queryargs, $catargs);
            }
        }

        $sql .= " ORDER BY qc.name, q.name";

        $rows = $DB->get_records_sql($sql, $queryargs, 0, $params['limit'] + 1);

        // Tag-Filter (inkl. Schwierigkeit) — in PHP, da Moodle Tags flexibel sind.
        $alltags = array_filter(array_map('trim', explode(',', $params['tags'])));
        if (!empty($params['difficulty'])) {
            $alltags[] = trim($params['difficulty']);
        }

        $questions = [];
        foreach ($rows as $row) {
            $qtags = self::get_question_tags((int)$row->id);

            // Tag-Filter anwenden.
            if (!empty($alltags)) {
                $tagnames = array_map('strtolower', array_column($qtags, 'name'));
                $match = true;
                foreach ($alltags as $filtertag) {
                    $found = false;
                    foreach ($tagnames as $tn) {
                        if (strpos($tn, strtolower($filtertag)) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $match = false;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            // Auch in Tags suchen (Freitextsuche).
            if (!empty($params['search'])) {
                $tagnames = array_map('strtolower', array_column($qtags, 'name'));
                $intitle = stripos($row->name, $params['search']) !== false;
                $intag = false;
                foreach ($tagnames as $tn) {
                    if (strpos($tn, strtolower($params['search'])) !== false) {
                        $intag = true;
                        break;
                    }
                }
                if (!$intitle && !$intag) {
                    continue;
                }
            }

            $questions[] = [
                'id' => (int)$row->id,
                'name' => $row->name,
                'qtype' => $row->qtype,
                'categoryid' => (int)$row->categoryid,
                'categoryname' => $row->categoryname,
                'contextid' => (int)$row->categorycontextid,
                'tags' => $qtags,
            ];

            if (count($questions) >= $params['limit']) {
                break;
            }
        }

        return [
            'questions' => $questions,
            'total' => count($questions),
        ];
    }

    /**
     * Beschreibt die Rueckgabewerte der Suchfunktion.
     *
     * @return external_single_structure Rueckgabedefinition.
     */
    public static function search_questions_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT),
                    'name' => new external_value(PARAM_TEXT),
                    'qtype' => new external_value(PARAM_ALPHA),
                    'categoryid' => new external_value(PARAM_INT),
                    'categoryname' => new external_value(PARAM_TEXT),
                    'contextid' => new external_value(PARAM_INT),
                    'tags' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT),
                            'name' => new external_value(PARAM_TEXT),
                        ])
                    ),
                ])
            ),
            'total' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Beschreibt die Parameter zum Laden der Kategorien.
     *
     * @return external_function_parameters Parameterdefinition.
     */
    public static function get_categories_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Aktueller Kontext', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Laedt alle durchsuchbaren Fragekategorien.
     *
     * @param string $scope Suchbereich: all, course oder system.
     * @param int $contextid ID des aktuellen Kontexts.
     * @return array Liste der Kategorien.
     */
    public static function get_categories(string $scope, int $contextid): array {
        global $DB;

        $params = self::validate_parameters(
            self::get_categories_parameters(),
            ['scope' => $scope, 'contextid' => $contextid]
        );
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);
        if (empty($contextids)) {
            return ['categories' => []];
        }

        [$ctxsql, $ctxargs] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $rows = $DB->get_records_sql(
            "SELECT qc.id, qc.name, qc.contextid, ctx.contextlevel,
                    ctx.instanceid, COUNT(qbe.id) AS questioncount
               FROM {question_categories} qc
               JOIN {context} ctx ON ctx.id = qc.contextid
          LEFT JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
              WHERE qc.contextid $ctxsql
           GROUP BY qc.id, qc.name, qc.contextid, ctx.contextlevel, ctx.instanceid
           ORDER BY qc.name",
            $ctxargs
        );

        $cats = [];
        foreach ($rows as $r) {
            $label = self::context_label((int)$r->contextlevel, (int)$r->instanceid);
            $cats[] = [
                'id' => (int)$r->id,
                'name' => $r->name,
                'contextlabel' => $label,
                'questioncount' => (int)$r->questioncount,
            ];
        }
        return ['categories' => $cats];
    }

    /**
     * Beschreibt die Rueckgabewerte zum Laden der Kategorien.
     *
     * @return external_single_structure Rueckgabedefinition.
     */
    public static function get_categories_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT),
                    'name' => new external_value(PARAM_TEXT),
                    'contextlabel' => new external_value(PARAM_TEXT),
                    'questioncount' => new external_value(PARAM_INT),
                ])
            ),
        ]);
    }

    /**
     * Beschreibt die Parameter zum Laden der Fragetypen.
     *
     * @return external_function_parameters Parameterdefinition.
     */
    public static function get_questiontypes_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Aktueller Kontext', VALUE_DEFAULT, 1),
            'source' => new external_value(PARAM_ALPHA, 'existing|installed', VALUE_DEFAULT, 'installed'),
        ]);
    }

    /**
     * Laedt die verfuegbaren Fragetypen.
     *
     * @param string $scope Suchbereich: all, course oder system.
     * @param int $contextid ID des aktuellen Kontexts.
     * @param string $source Quelle: installed oder existing.
     * @return array Liste der Fragetypen.
     */
    public static function get_questiontypes(string $scope, int $contextid, string $source = 'installed'): array {
        global $DB, $CFG;

        $params = self::validate_parameters(
            self::get_questiontypes_parameters(),
            ['scope' => $scope, 'contextid' => $contextid, 'source' => $source]
        );
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        $qtypes = [];

        if ($params['source'] === 'installed') {
            // Alle installierten qtype-Plugins laden.
            $plugintypes = core_component::get_plugin_list('qtype');
            foreach ($plugintypes as $key => $path) {
                // Systemtypen überspringen.
                if (in_array($key, ['missingtype', 'unknowntype'])) {
                    continue;
                }
                $plugin = 'qtype_' . $key;
                if (get_string_manager()->string_exists('pluginname', $plugin)) {
                    $label = get_string('pluginname', $plugin);
                } else {
                    $label = ucfirst($key);
                }
                $qtypes[$key] = $label;
            }
        } else {
            // Nur Typen die tatsächlich in der Fragebank vorhanden sind.
            $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);
            if (empty($contextids)) {
                return ['qtypes' => []];
            }

            [$ctxsql, $ctxargs] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
            $rows = $DB->get_records_sql(
                "SELECT DISTINCT q.qtype
                   FROM {question} q
                   JOIN {question_versions} qv       ON qv.questionid        = q.id
                   JOIN {question_bank_entries} qbe   ON qbe.id              = qv.questionbankentryid
                   JOIN {question_categories} qc      ON qc.id               = qbe.questioncategoryid
                  WHERE qc.contextid $ctxsql
                    AND q.parent = 0
                    AND qv.status = 'ready'
               ORDER BY q.qtype",
                $ctxargs
            );
            foreach ($rows as $r) {
                $plugin = 'qtype_' . $r->qtype;
                $label = get_string_manager()->string_exists('pluginname', $plugin)
                    ? get_string('pluginname', $plugin) : ucfirst($r->qtype);
                $qtypes[$r->qtype] = $label;
            }
        }

        // Alphabetisch nach Anzeigename sortieren.
        asort($qtypes);

        $result = [];
        foreach ($qtypes as $key => $label) {
            $result[] = ['key' => $key, 'label' => $label];
        }
        return ['qtypes' => $result];
    }

    /**
     * Beschreibt die Rueckgabewerte zum Laden der Fragetypen.
     *
     * @return external_single_structure Rueckgabedefinition.
     */
    public static function get_questiontypes_returns(): external_single_structure {
        return new external_single_structure([
            'qtypes' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT),
                    'label' => new external_value(PARAM_TEXT),
                ])
            ),
        ]);
    }
    /**
     * Beschreibt die Parameter der Exportfunktion.
     *
     * @return external_function_parameters Parameterdefinition.
     */
    public static function export_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_value(PARAM_TEXT, 'Kommagetrennte Fragen-IDs'),
            'format' => new external_value(PARAM_ALPHA, 'xml|csv|gift', VALUE_DEFAULT, 'xml'),
            'contextid' => new external_value(PARAM_INT, 'Kontext', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Exportiert die ausgewaehlten Fragen in das gewuenschte Format.
     *
     * @param string $questionids Kommagetrennte Fragen-IDs.
     * @param string $format Zielformat: xml, csv oder gift.
     * @param int $contextid ID des aktuellen Kontexts.
     * @return array Angaben zur erzeugten Exportdatei.
     */
    public static function export_questions(string $questionids, string $format, int $contextid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::export_questions_parameters(), [
            'questionids' => $questionids,
            'format' => $format,
            'contextid' => $contextid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        // Export erfordert eigene Capability (nicht für Gäste).
        if (!has_capability('block/questionfilter:export', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        $ids = array_filter(array_map('intval', explode(',', $params['questionids'])));
        if (empty($ids)) {
            throw new moodle_exception('noquestionsselected', 'block_questionfilter');
        }

        require_once($CFG->libdir . '/questionlib.php');

        switch ($params['format']) {
            case 'csv':
                $content = self::export_csv($ids);
                $filename = 'questions_' . date('Ymd_His') . '.csv';
                $mimetype = 'text/csv';
                break;
            case 'gift':
                $content = self::export_gift($ids);
                $filename = 'questions_' . date('Ymd_His') . '.txt';
                $mimetype = 'text/plain';
                break;
            default:
                $content = self::export_moodle_xml($ids);
                $filename = 'questions_' . date('Ymd_His') . '.xml';
                $mimetype = 'application/xml';
                break;
        }

        // Datei im temporären Moodle-Bereich speichern, URL zurückgeben.
        $fs = get_file_storage();
        $sysctx = context_system::instance();
        $fs->delete_area_files($sysctx->id, 'block_questionfilter', 'export', $params['contextid']);

        $fileinfo = [
            'contextid' => $sysctx->id,
            'component' => 'block_questionfilter',
            'filearea' => 'export',
            'itemid' => $params['contextid'],
            'filepath' => '/',
            'filename' => $filename,
        ];
        $file = $fs->create_file_from_string($fileinfo, $content);
        $url = moodle_url::make_pluginfile_url(
            $sysctx->id,
            'block_questionfilter',
            'export',
            $params['contextid'],
            '/',
            $filename
        )->out(false);

        return [
            'success' => true,
            'url' => $url,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'count' => count($ids),
        ];
    }

    /**
     * Beschreibt die Rueckgabewerte der Exportfunktion.
     *
     * @return external_single_structure Rueckgabedefinition.
     */
    public static function export_questions_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL),
            'url' => new external_value(PARAM_URL),
            'filename' => new external_value(PARAM_FILE),
            'mimetype' => new external_value(PARAM_TEXT),
            'count' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Alle durchsuchbaren Kontext-IDs je nach Suchbereich ermitteln.
     *
     * Moodle 4.x:  Fragen liegen in Kurs- und System-Kontexten.
     * Moodle 5.0+: Fragebanken sind eigenständige mod_qbank-Aktivitäten.
     *              Jede Fragebank hat einen eigenen CONTEXT_MODULE-Kontext.
     *              Zusätzlich gibt es pro Kurs eine automatisch migrierte
     *              „shared question bank" ebenfalls als mod_qbank-Instanz.
     *
     * Scopes:
     * - 'system' : nur System-Kontext
     * - 'course' : aktueller Kurs-Kontext (+ mod_qbank-Kontexte im Kurs bei Moodle 5)
     * - 'all'    : System + alle Kurse + alle mod_qbank-Kontexte (Moodle 5)
     *
     * @param string $scope Suchbereich: all, course oder system.
     * @param int $contextid ID des aktuellen Kontexts.
     * @return array Liste der Kontext-IDs.
     */
    private static function get_searchable_contextids(string $scope, int $contextid): array {
        global $DB, $CFG;

        $sysctx = context_system::instance();
        $ismoodle5 = (int)$CFG->version >= 2024100700; // Entspricht Moodle 5.0.

        if ($scope === 'system') {
            return [$sysctx->id];
        }

        if ($scope === 'course') {
            $ctx = context::instance_by_id($contextid);
            $coursectx = $ctx->get_course_context(false);
            $ids = $coursectx ? [$coursectx->id, $sysctx->id] : [$sysctx->id];

            // Moodle 5+: mod_qbank-Kontexte des Kurses ergänzen.
            if ($ismoodle5 && $coursectx) {
                $qbankctxids = self::get_qbank_module_contextids($coursectx->instanceid);
                $ids = array_unique(array_merge($ids, $qbankctxids));
            }
            return $ids;
        }

        // Suchbereich all umfasst System- und alle Kurs-Kontexte.
        $coursectxids = $DB->get_fieldset_sql(
            "SELECT id FROM {context} WHERE contextlevel = :lvl",
            ['lvl' => CONTEXT_COURSE]
        );
        $ids = array_merge([$sysctx->id], $coursectxids);

        // Moodle 5+: alle mod_qbank-Modul-Kontexte systemweit ergänzen.
        if ($ismoodle5) {
            $qbankctxids = self::get_qbank_module_contextids(null);
            $ids = array_unique(array_merge($ids, $qbankctxids));
        }

        return $ids;
    }

    /**
     * Liefert alle CONTEXT_MODULE-Kontexte von mod_qbank-Instanzen.
     * $courseid = null  → systemweit
     * $courseid = int   → nur dieser Kurs
     *
     * Moodle 5.0+ speichert Fragebanken als mod_qbank-Aktivitäten.
     * Die Fragekategorien hängen am Modul-Kontext (contextlevel = 70).
     *
     * @param int|null $courseid Kurs-ID oder null fuer alle Kurse.
     * @return array Liste der Kontext-IDs.
     */
    private static function get_qbank_module_contextids(?int $courseid): array {
        global $DB;

        // Mod_qbank existiert nur in Moodle 5+; prüfen ob Modul vorhanden ist.
        if (!$DB->get_record('modules', ['name' => 'qbank'], 'id', IGNORE_MISSING)) {
            return [];
        }

        $sql = "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid
                   JOIN {modules} m ON m.id = cm.module
                  WHERE ctx.contextlevel = :ctxlvl
                    AND m.name = 'qbank'";
        $args = ['ctxlvl' => CONTEXT_MODULE];

        if ($courseid !== null) {
            $sql  .= " AND cm.course = :courseid";
            $args['courseid'] = $courseid;
        }

        return $DB->get_fieldset_sql($sql, $args) ?: [];
    }

    /**
     * Tags einer Frage laden.
     *
     * @param int $questionid ID der Frage.
     * @return array Liste der Tags.
     */
    private static function get_question_tags(int $questionid): array {
        global $DB;

        $sql = "SELECT t.id, t.name
                  FROM {tag} t
                  JOIN {tag_instance} ti ON ti.tagid = t.id
                 WHERE ti.itemtype = 'question'
                   AND ti.itemid = :qid
                   AND ti.component = 'core_question'";
        $rows = $DB->get_records_sql($sql, ['qid' => $questionid]);
        $result = [];
        foreach ($rows as $r) {
            $result[] = ['id' => (int)$r->id, 'name' => $r->name];
        }
        return $result;
    }

    /**
     * Lesbares Label für den Kontext einer Kategorie.
     * Für mod_qbank-Instanzen (Moodle 5) wird der tatsächliche
     * Name der Fragebank aus der qbank-Instanztabelle gelesen.
     *
     * @param int $contextlevel Kontextebene.
     * @param int $instanceid ID der Kontextinstanz.
     * @return string Lesbare Bezeichnung.
     */
    private static function context_label(int $contextlevel, int $instanceid): string {
        global $DB;
        switch ($contextlevel) {
            case CONTEXT_SYSTEM:
                return get_string('coresystem', 'block_questionfilter');

            case CONTEXT_COURSECAT:
                $name = $DB->get_field('course_categories', 'name', ['id' => $instanceid]);
                return $name ? 'Kategorie: ' . $name : 'Kategorie #' . $instanceid;

            case CONTEXT_COURSE:
                $name = $DB->get_field('course', 'fullname', ['id' => $instanceid]);
                return $name ?: 'Kurs #' . $instanceid;

            case CONTEXT_MODULE:
                // Kursmodul laden.
                $cm = $DB->get_record('course_modules', ['id' => $instanceid], 'id,course,module,instance');
                if (!$cm) {
                    return 'Modul #' . $instanceid;
                }

                $coursename = $DB->get_field('course', 'shortname', ['id' => $cm->course]) ?: 'Kurs';

                // Modultyp ermitteln.
                $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);

                if ($modname === 'qbank') {
                    // Name der Fragebank-Instanz aus mod_qbank holen.
                    $bankname = $DB->get_field('qbank', 'name', ['id' => $cm->instance]);
                    if ($bankname) {
                        return $coursename . ' › ' . $bankname;
                    }
                } else if ($modname === 'quiz') {
                    $quizname = $DB->get_field('quiz', 'name', ['id' => $cm->instance]);
                    if ($quizname) {
                        return $coursename . ' › Quiz: ' . $quizname;
                    }
                }

                // Fallback: Kursname reicht.
                return $coursename;

            default:
                return 'Kontext #' . $instanceid;
        }
    }

    /**
     * Moodle XML-Export über die native qformat_xml-Engine.
     *
     * qformat_xml::exportprocess() erwartet Fragen im alten stdClass-Format
     * (wie aus der DB), nicht question_definition-Objekte von load_question().
     * get_question_options() lädt Antworten, Hints und Unterfragen (multianswer)
     * direkt in die stdClass — das ist der korrekte Weg für den Export.
     *
     * @param array $ids Liste der Fragen-IDs.
     * @return string Inhalt der Exportdatei.
     */
    private static function export_moodle_xml(array $ids): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        // Fragen als rohe DB-Datensätze laden (stdClass, nicht question_definition).
        [$insql, $args] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'qid');
        $rows = $DB->get_records_sql(
            "SELECT q.*, qc.id AS category
               FROM {question} q
               JOIN {question_versions} qv  ON qv.questionid        = q.id
               JOIN {question_bank_entries} qbe ON qbe.id           = qv.questionbankentryid
               JOIN {question_categories} qc    ON qc.id            = qbe.questioncategoryid
              WHERE q.id $insql
                AND q.parent = 0",
            $args
        );

        if (empty($rows)) {
            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n</quiz>\n";
        }

        // Get_question_options() befüllt jede Frage mit Antworten, Hints,.
        // Und bei qtype_multianswer mit den Unterfragen (subquestions als stdClass).
        $questions = [];
        foreach ($rows as $q) {
            get_question_options($q); // Ergaenzt $q um Antworten und Hinweise.
            $questions[] = $q;
        }

        // Exportprocess(true) gibt den XML-String direkt zurück.
        $qformat = new qformat_xml();
        $qformat->setQuestions($questions);
        $qformat->setContexts([context_system::instance()]);
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);

        if (!$qformat->exportpreprocess()) {
            throw new moodle_exception('exportfailed', 'block_questionfilter');
        }

        $content = $qformat->exportprocess(true);

        if ($content === false || $content === '') {
            throw new moodle_exception('exportfailed', 'block_questionfilter');
        }

        return $content;
    }

    /**
     * CSV-Export: ID, Name, Typ, Kategorie, Tags.
     * RFC 4180-konformes Quoting (doppelte Anführungszeichen escapen).
     *
     * @param array $ids Liste der Fragen-IDs.
     * @return string Inhalt der Exportdatei.
     */
    private static function export_csv(array $ids): string {
        global $DB;

        [$insql, $args] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'q');
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.name, q.qtype, qc.name AS categoryname
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE q.id $insql",
            $args
        );

        $csvquote = function (string $s): string {
            return '"' . str_replace('"', '""', $s) . '"';
        };

        $lines = [implode(',', array_map($csvquote, ['ID', 'Name', 'Typ', 'Kategorie', 'Tags']))];
        foreach ($questions as $q) {
            $tags = self::get_question_tags((int)$q->id);
            $tagstr = implode('; ', array_column($tags, 'name'));
            $lines[] = implode(',', [
                $csvquote((string)$q->id),
                $csvquote($q->name),
                $csvquote($q->qtype),
                $csvquote($q->categoryname),
                $csvquote($tagstr),
            ]);
        }
        return implode("\r\n", $lines);
    }

    /**
     * GIFT-Export über Moodles native qformat_gift-Engine (falls verfügbar),
     * sonst einfaches Fallback-Format.
     *
     * @param array $ids Liste der Fragen-IDs.
     * @return string Inhalt der Exportdatei.
     */
    private static function export_gift(array $ids): string {
        global $CFG, $DB;

        $giftformatfile = $CFG->dirroot . '/question/format/gift/format.php';

        if (file_exists($giftformatfile)) {
            require_once($CFG->libdir . '/questionlib.php');
            require_once($CFG->dirroot . '/question/format.php');
            require_once($giftformatfile);

            $questions = [];
            foreach ($ids as $qid) {
                try {
                    $q = question_bank::load_question($qid);
                    if ($q) {
                        $questions[] = $q;
                    }
                } catch (Exception $e) {
                    // Fehlerhafte Frage wird uebersprungen.
                    debugging($e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            if (!empty($questions)) {
                $qformat = new qformat_gift();
                $output = '// GIFT Export – ' . date('Y-m-d H:i:s') . "\n\n";
                foreach ($questions as $q) {
                    try {
                        $line = $qformat->writequestion($q);
                        if ($line) {
                            $output .= $line . "\n";
                        }
                    } catch (Exception $e) {
                        // Fehlerhafte Frage wird uebersprungen.
                        debugging($e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
                return $output;
            }
        }

        // Fallback: einfaches Textformat.
        [$insql, $args] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'q');
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.name, q.qtype, q.questiontext, qc.name AS categoryname
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE q.id $insql",
            $args
        );

        $out = '// GIFT Export – ' . date('Y-m-d H:i:s') . "\n\n";
        foreach ($questions as $q) {
            $text = strip_tags($q->questiontext ?? '');
            $out .= "// Kategorie: {$q->categoryname} | Typ: {$q->qtype}\n";
            $out .= "::{$q->name}::{$text} {}\n\n";
        }
        return $out;
    }
}
