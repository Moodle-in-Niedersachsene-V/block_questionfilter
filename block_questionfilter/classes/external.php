<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class block_questionfilter_external extends external_api {

    // ---------------------------------------------------------------
    // search_questions
    // ---------------------------------------------------------------
    public static function search_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'     => new external_value(PARAM_TEXT,  'Suchbegriff', VALUE_DEFAULT, ''),
            'categories' => new external_value(PARAM_TEXT,  'Kommagetrennte Kategorie-IDs (leer = alle)', VALUE_DEFAULT, ''),
            'types'      => new external_value(PARAM_TEXT,  'Kommagetrennte Fragetypen', VALUE_DEFAULT, ''),
            'difficulty' => new external_value(PARAM_TEXT,  'Schwierigkeitsstufe (Tag)', VALUE_DEFAULT, ''),
            'tags'       => new external_value(PARAM_TEXT,  'Kommagetrennte Tags', VALUE_DEFAULT, ''),
            'scope'      => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid'  => new external_value(PARAM_INT,   'Aktueller Kontext', VALUE_DEFAULT, 1),
            'limit'      => new external_value(PARAM_INT,   'Max. Ergebnisse', VALUE_DEFAULT, 200),
        ]);
    }

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
            'search'     => $search,
            'categories' => $categories,
            'types'      => $types,
            'difficulty' => $difficulty,
            'tags'       => $tags,
            'scope'      => $scope,
            'contextid'  => $contextid,
            'limit'      => $limit,
        ]);

        // Berechtigungsprüfung — eigene Capability, Gäste können erlaubt werden
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        // Kontexte sammeln je nach Suchbereich
        $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);

        if (empty($contextids)) {
            return ['questions' => [], 'total' => 0];
        }

        list($ctxsql, $ctxargs) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        // Basis-Query über question + question_categories
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

        // Freitext-Suche
        if (!empty($params['search'])) {
            $sql .= " AND " . $DB->sql_like('q.name', ':search', false);
            $queryargs['search'] = '%' . $DB->sql_like_escape($params['search']) . '%';
        }

        // Fragetyp-Filter
        if (!empty($params['types'])) {
            $typeList = array_filter(array_map('trim', explode(',', $params['types'])));
            if ($typeList) {
                list($typesql, $typeargs) = $DB->get_in_or_equal($typeList, SQL_PARAMS_NAMED, 'type');
                $sql .= " AND q.qtype $typesql";
                $queryargs = array_merge($queryargs, $typeargs);
            }
        }

        // Kategorie-Filter
        if (!empty($params['categories'])) {
            $catIds = array_filter(array_map('intval', explode(',', $params['categories'])));
            if ($catIds) {
                list($catsql, $catargs) = $DB->get_in_or_equal($catIds, SQL_PARAMS_NAMED, 'cat');
                $sql .= " AND qbe.questioncategoryid $catsql";
                $queryargs = array_merge($queryargs, $catargs);
            }
        }

        $sql .= " ORDER BY qc.name, q.name";

        $rows = $DB->get_records_sql($sql, $queryargs, 0, $params['limit'] + 1);

        // Tag-Filter (inkl. Schwierigkeit) — in PHP, da Moodle Tags flexibel sind
        $allTags = array_filter(array_map('trim', explode(',', $params['tags'])));
        if (!empty($params['difficulty'])) {
            $allTags[] = trim($params['difficulty']);
        }

        $questions = [];
        foreach ($rows as $row) {
            $qtags = self::get_question_tags((int)$row->id);

            // Tag-Filter anwenden
            if (!empty($allTags)) {
                $tagnames = array_map('strtolower', array_column($qtags, 'name'));
                $match = true;
                foreach ($allTags as $filterTag) {
                    $found = false;
                    foreach ($tagnames as $tn) {
                        if (strpos($tn, strtolower($filterTag)) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) { $match = false; break; }
                }
                if (!$match) continue;
            }

            // Auch in Tags suchen (Freitextsuche)
            if (!empty($params['search'])) {
                $tagnames = array_map('strtolower', array_column($qtags, 'name'));
                $inTitle = stripos($row->name, $params['search']) !== false;
                $inTag   = false;
                foreach ($tagnames as $tn) {
                    if (strpos($tn, strtolower($params['search'])) !== false) {
                        $inTag = true; break;
                    }
                }
                if (!$inTitle && !$inTag) continue;
            }

            $questions[] = [
                'id'           => (int)$row->id,
                'name'         => $row->name,
                'qtype'        => $row->qtype,
                'categoryid'   => (int)$row->categoryid,
                'categoryname' => $row->categoryname,
                'contextid'    => (int)$row->categorycontextid,
                'tags'         => $qtags,
            ];

            if (count($questions) >= $params['limit']) break;
        }

        return [
            'questions' => $questions,
            'total'     => count($questions),
        ];
    }

    public static function search_questions_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT),
                    'name'         => new external_value(PARAM_TEXT),
                    'qtype'        => new external_value(PARAM_ALPHA),
                    'categoryid'   => new external_value(PARAM_INT),
                    'categoryname' => new external_value(PARAM_TEXT),
                    'contextid'    => new external_value(PARAM_INT),
                    'tags'         => new external_multiple_structure(
                        new external_single_structure([
                            'id'   => new external_value(PARAM_INT),
                            'name' => new external_value(PARAM_TEXT),
                        ])
                    ),
                ])
            ),
            'total' => new external_value(PARAM_INT),
        ]);
    }

    // ---------------------------------------------------------------
    // get_categories  — alle durchsuchbaren Kategorien laden
    // ---------------------------------------------------------------
    public static function get_categories_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope'     => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Aktueller Kontext', VALUE_DEFAULT, 1),
        ]);
    }

    public static function get_categories(string $scope, int $contextid): array {
        global $DB;

        $params  = self::validate_parameters(self::get_categories_parameters(),
            ['scope' => $scope, 'contextid' => $contextid]);
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);
        if (empty($contextids)) return ['categories' => []];

        list($ctxsql, $ctxargs) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
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
                'id'            => (int)$r->id,
                'name'          => $r->name,
                'contextlabel'  => $label,
                'questioncount' => (int)$r->questioncount,
            ];
        }
        return ['categories' => $cats];
    }

    public static function get_categories_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(
                new external_single_structure([
                    'id'            => new external_value(PARAM_INT),
                    'name'          => new external_value(PARAM_TEXT),
                    'contextlabel'  => new external_value(PARAM_TEXT),
                    'questioncount' => new external_value(PARAM_INT),
                ])
            ),
        ]);
    }

    // ---------------------------------------------------------------
    // get_questiontypes — nur tatsächlich vorhandene Typen laden
    // ---------------------------------------------------------------
    public static function get_questiontypes_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope'     => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT,   'Aktueller Kontext',  VALUE_DEFAULT, 1),
            'source'    => new external_value(PARAM_ALPHA, 'existing|installed', VALUE_DEFAULT, 'installed'),
        ]);
    }

    public static function get_questiontypes(string $scope, int $contextid, string $source = 'installed'): array {
        global $DB, $CFG;

        $params  = self::validate_parameters(self::get_questiontypes_parameters(),
            ['scope' => $scope, 'contextid' => $contextid, 'source' => $source]);
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_questionfilter');
        }

        $qtypes = [];

        if ($params['source'] === 'installed') {
            // Alle installierten qtype-Plugins laden
            $plugintypes = core_component::get_plugin_list('qtype');
            foreach ($plugintypes as $key => $path) {
                // Systemtypen überspringen
                if (in_array($key, ['missingtype', 'unknowntype'])) continue;
                $plugin = 'qtype_' . $key;
                if (get_string_manager()->string_exists('pluginname', $plugin)) {
                    $label = get_string('pluginname', $plugin);
                } else {
                    $label = ucfirst($key);
                }
                $qtypes[$key] = $label;
            }
        } else {
            // Nur Typen die tatsächlich in der Fragebank vorhanden sind
            $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);
            if (empty($contextids)) return ['qtypes' => []];

            list($ctxsql, $ctxargs) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
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
                $label  = get_string_manager()->string_exists('pluginname', $plugin)
                    ? get_string('pluginname', $plugin) : ucfirst($r->qtype);
                $qtypes[$r->qtype] = $label;
            }
        }

        // Alphabetisch nach Anzeigename sortieren
        asort($qtypes);

        $result = [];
        foreach ($qtypes as $key => $label) {
            $result[] = ['key' => $key, 'label' => $label];
        }
        return ['qtypes' => $result];
    }

    public static function get_questiontypes_returns(): external_single_structure {
        return new external_single_structure([
            'qtypes' => new external_multiple_structure(
                new external_single_structure([
                    'key'   => new external_value(PARAM_ALPHANUMEXT),
                    'label' => new external_value(PARAM_TEXT),
                ])
            ),
        ]);
    }
    public static function export_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_value(PARAM_TEXT, 'Kommagetrennte Fragen-IDs'),
            'format'      => new external_value(PARAM_ALPHA, 'xml|csv|gift', VALUE_DEFAULT, 'xml'),
            'contextid'   => new external_value(PARAM_INT, 'Kontext', VALUE_DEFAULT, 1),
        ]);
    }

    public static function export_questions(string $questionids, string $format, int $contextid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::export_questions_parameters(), [
            'questionids' => $questionids,
            'format'      => $format,
            'contextid'   => $contextid,
        ]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);

        // Export erfordert eigene Capability (nicht für Gäste)
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
                $content  = self::export_csv($ids);
                $filename = 'questions_' . date('Ymd_His') . '.csv';
                $mimetype = 'text/csv';
                break;
            case 'gift':
                $content  = self::export_gift($ids);
                $filename = 'questions_' . date('Ymd_His') . '.txt';
                $mimetype = 'text/plain';
                break;
            default: // xml
                $content  = self::export_moodle_xml($ids);
                $filename = 'questions_' . date('Ymd_His') . '.xml';
                $mimetype = 'application/xml';
                break;
        }

        // Datei im temporären Moodle-Bereich speichern, URL zurückgeben
        $fs      = get_file_storage();
        $sysctx  = context_system::instance();
        $fs->delete_area_files($sysctx->id, 'block_questionfilter', 'export', $params['contextid']);

        $fileinfo = [
            'contextid' => $sysctx->id,
            'component' => 'block_questionfilter',
            'filearea'  => 'export',
            'itemid'    => $params['contextid'],
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $file = $fs->create_file_from_string($fileinfo, $content);
        $url  = moodle_url::make_pluginfile_url(
            $sysctx->id, 'block_questionfilter', 'export',
            $params['contextid'], '/', $filename
        )->out(false);

        return [
            'success'  => true,
            'url'      => $url,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'count'    => count($ids),
        ];
    }

    public static function export_questions_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL),
            'url'      => new external_value(PARAM_URL),
            'filename' => new external_value(PARAM_FILE),
            'mimetype' => new external_value(PARAM_TEXT),
            'count'    => new external_value(PARAM_INT),
        ]);
    }

    // ---------------------------------------------------------------
    // Hilfsmethoden
    // ---------------------------------------------------------------

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
     */
    private static function get_searchable_contextids(string $scope, int $contextid): array {
        global $DB, $CFG;

        $sysctx    = context_system::instance();
        $isMoodle5 = (int)$CFG->version >= 2024100700; // Moodle 5.0 = 2024100700

        if ($scope === 'system') {
            return [$sysctx->id];
        }

        if ($scope === 'course') {
            $ctx       = context::instance_by_id($contextid);
            $courseCtx = $ctx->get_course_context(false);
            $ids       = $courseCtx ? [$courseCtx->id, $sysctx->id] : [$sysctx->id];

            // Moodle 5+: mod_qbank-Kontexte des Kurses ergänzen
            if ($isMoodle5 && $courseCtx) {
                $qbankCtxIds = self::get_qbank_module_contextids($courseCtx->instanceid);
                $ids = array_unique(array_merge($ids, $qbankCtxIds));
            }
            return $ids;
        }

        // 'all': System + alle Kurs-Kontexte
        $courseCtxIds = $DB->get_fieldset_sql(
            "SELECT id FROM {context} WHERE contextlevel = :lvl",
            ['lvl' => CONTEXT_COURSE]
        );
        $ids = array_merge([$sysctx->id], $courseCtxIds);

        // Moodle 5+: alle mod_qbank-Modul-Kontexte systemweit ergänzen
        if ($isMoodle5) {
            $qbankCtxIds = self::get_qbank_module_contextids(null);
            $ids = array_unique(array_merge($ids, $qbankCtxIds));
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
     */
    private static function get_qbank_module_contextids(?int $courseid): array {
        global $DB;

        // mod_qbank existiert nur in Moodle 5+; prüfen ob Modul vorhanden ist
        if (!$DB->get_record('modules', ['name' => 'qbank'], 'id', IGNORE_MISSING)) {
            return [];
        }

        $sql  = "SELECT ctx.id
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
                // Kursmodul laden
                $cm = $DB->get_record('course_modules', ['id' => $instanceid], 'id,course,module,instance');
                if (!$cm) return 'Modul #' . $instanceid;

                $coursename = $DB->get_field('course', 'shortname', ['id' => $cm->course]) ?: 'Kurs';

                // Modultyp ermitteln
                $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);

                if ($modname === 'qbank') {
                    // Name der Fragebank-Instanz aus mod_qbank holen
                    $bankname = $DB->get_field('qbank', 'name', ['id' => $cm->instance]);
                    if ($bankname) {
                        return $coursename . ' › ' . $bankname;
                    }
                } elseif ($modname === 'quiz') {
                    $quizname = $DB->get_field('quiz', 'name', ['id' => $cm->instance]);
                    if ($quizname) {
                        return $coursename . ' › Quiz: ' . $quizname;
                    }
                }

                // Fallback: Kursname reicht
                return $coursename;

            default:
                return 'Kontext #' . $instanceid;
        }
    }

    // --- Export-Formate ---

    /**
     * Moodle XML-Export über die native qformat_xml-Engine.
     *
     * qformat_xml::exportprocess() erwartet Fragen im alten stdClass-Format
     * (wie aus der DB), nicht question_definition-Objekte von load_question().
     * get_question_options() lädt Antworten, Hints und Unterfragen (multianswer)
     * direkt in die stdClass — das ist der korrekte Weg für den Export.
     */
    private static function export_moodle_xml(array $ids): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        // Fragen als rohe DB-Datensätze laden (stdClass, nicht question_definition)
        list($insql, $args) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'qid');
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

        // get_question_options() befüllt jede Frage mit Antworten, Hints,
        // und bei qtype_multianswer mit den Unterfragen (subquestions als stdClass)
        $questions = [];
        foreach ($rows as $q) {
            get_question_options($q);   // modifiziert $q in-place
            $questions[] = $q;
        }

        // exportprocess(true) gibt den XML-String direkt zurück
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
     */
    private static function export_csv(array $ids): string {
        global $DB;

        list($insql, $args) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'q');
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.name, q.qtype, qc.name AS categoryname
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE q.id $insql", $args
        );

        $csvquote = function(string $s): string {
            return '"' . str_replace('"', '""', $s) . '"';
        };

        $lines = [implode(',', array_map($csvquote, ['ID', 'Name', 'Typ', 'Kategorie', 'Tags']))];
        foreach ($questions as $q) {
            $tags    = self::get_question_tags((int)$q->id);
            $tagstr  = implode('; ', array_column($tags, 'name'));
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
     */
    private static function export_gift(array $ids): string {
        global $CFG, $DB;

        $giftFormatFile = $CFG->dirroot . '/question/format/gift/format.php';

        if (file_exists($giftFormatFile)) {
            require_once($CFG->libdir . '/questionlib.php');
            require_once($CFG->dirroot . '/question/format.php');
            require_once($giftFormatFile);

            $questions = [];
            foreach ($ids as $qid) {
                try {
                    $q = question_bank::load_question($qid);
                    if ($q) $questions[] = $q;
                } catch (Exception $e) {
                    // überspringen
                }
            }

            if (!empty($questions)) {
                $qformat = new qformat_gift();
                $output  = '// GIFT Export – ' . date('Y-m-d H:i:s') . "\n\n";
                foreach ($questions as $q) {
                    try {
                        $line = $qformat->writequestion($q);
                        if ($line) $output .= $line . "\n";
                    } catch (Exception $e) {
                        // überspringen
                    }
                }
                return $output;
            }
        }

        // Fallback: einfaches Textformat
        list($insql, $args) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'q');
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.name, q.qtype, q.questiontext, qc.name AS categoryname
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE q.id $insql", $args
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
