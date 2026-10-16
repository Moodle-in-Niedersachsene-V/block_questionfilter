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
 * Web service functions for block_fragenfilter.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service functions for the question bank filter.
 *
 * @package    block_fragenfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_fragenfilter_external extends external_api {
    /** Maximum requests per user within the rate limit window. */
    const RATE_LIMIT_REQUESTS = 30;

    /** Rate limit window length in seconds. */
    const RATE_LIMIT_WINDOW = 60;

    /** Absolute maximum number of returned results (DoS protection). */
    const MAX_RESULT_LIMIT = 200;

    /** Default result limit when the caller does not provide one. */
    const DEFAULT_RESULT_LIMIT = 200;

    /**
     * Checks the sliding rate limit for the given user.
     *
     * Throws a moodle_exception if the user has made more than RATE_LIMIT_REQUESTS
     * requests within RATE_LIMIT_WINDOW seconds.
     *
     * @param int $userid Moodle user ID.
     * @throws \moodle_exception If the rate limit is exceeded.
     */
    private static function check_rate_limit(int $userid): void {
        $cache = \cache::make('block_fragenfilter', 'ratelimit');
        $key   = 'rl_' . $userid;
        $now   = time();
        $data  = $cache->get($key);

        if ($data === false || ($now - $data['window_start']) >= self::RATE_LIMIT_WINDOW) {
            $cache->set($key, ['count' => 1, 'window_start' => $now]);
            return;
        }

        $data['count']++;
        $cache->set($key, $data);

        if ($data['count'] > self::RATE_LIMIT_REQUESTS) {
            $remaining = self::RATE_LIMIT_WINDOW - ($now - $data['window_start']);
            throw new \moodle_exception('ratelimitexceeded', 'block_fragenfilter', '', (object)[
                'seconds' => max(1, (int)$remaining),
            ]);
        }
    }

    /**
     * Describes the search parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function search_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search term', VALUE_DEFAULT, ''),
            'categories' => new external_value(PARAM_TEXT, 'Comma-separated category IDs (empty = all)', VALUE_DEFAULT, ''),
            'types' => new external_value(PARAM_TEXT, 'Comma-separated question types', VALUE_DEFAULT, ''),
            'difficulty' => new external_value(PARAM_TEXT, 'Difficulty level (tag)', VALUE_DEFAULT, ''),
            'tags' => new external_value(PARAM_TEXT, 'Comma-separated tags', VALUE_DEFAULT, ''),
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Current context', VALUE_DEFAULT, 1),
            'limit' => new external_value(PARAM_INT, 'Maximum results', VALUE_DEFAULT, 200),
        ]);
    }

    /**
     * Searches questions using the given filters.
     *
     * @param string $search Free-text search term.
     * @param string $categories Comma-separated category IDs; empty means all.
     * @param string $types Comma-separated question types.
     * @param string $difficulty Difficulty level as a tag.
     * @param string $tags Comma-separated tags.
     * @param string $scope Search scope: all, course or system.
     * @param int $contextid Current context ID.
     * @param int $limit Maximum number of results.
     * @return array Matching questions.
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
        global $DB, $USER;

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

        // Cap the limit at the configured maximum (DoS protection).
        $maxlimit = (int)(get_config('block_fragenfilter', 'resultlimit') ?: 200);
        if ($params['limit'] < 1 || $params['limit'] > $maxlimit) {
            $params['limit'] = $maxlimit;
        }

        // Check the dedicated capability, which can be granted to guests.
        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        // Public search validates guest access without retaining a guest login.
        $anonymous = !isloggedin();
        $originaluser = clone $USER;
        if ($anonymous) {
            \core\session\manager::set_user(guest_user());
        }
        try {
            self::validate_context($context);
        } finally {
            if ($anonymous) {
                \core\session\manager::set_user($originaluser);
            }
        }

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        // Rate limit: at most RATE_LIMIT_REQUESTS per user within RATE_LIMIT_WINDOW seconds.
        self::check_rate_limit((int)$USER->id);

        // Collect contexts according to the search scope.
        $contextids = self::get_searchable_contextids($params['scope'], $params['contextid']);

        if (empty($contextids)) {
            return ['questions' => [], 'total' => 0];
        }

        [$ctxsql, $ctxargs] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

        // Base query joining question and question_categories.
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

        // Free-text search: question title OR tags.
        if (!empty($params['search'])) {
            $searchparam = '%' . $DB->sql_like_escape($params['search']) . '%';
            $sql .= " AND (
                " . $DB->sql_like('q.name', ':searchname', false) . "
                OR EXISTS (
                    SELECT 1
                      FROM {tag_instance} ti
                      JOIN {tag} t ON t.id = ti.tagid
                     WHERE ti.itemid = q.id
                       AND ti.itemtype = 'question'
                       AND ti.component = 'core_question'
                       AND " . $DB->sql_like('t.name', ':searchtag', false) . "
                )
            )";
            $queryargs['searchname'] = $searchparam;
            $queryargs['searchtag']  = $searchparam;
        }

        // Question type filter.
        if (!empty($params['types'])) {
            $typelist = array_filter(array_map('trim', explode(',', $params['types'])));
            if ($typelist) {
                [$typesql, $typeargs] = $DB->get_in_or_equal($typelist, SQL_PARAMS_NAMED, 'type');
                $sql .= " AND q.qtype $typesql";
                $queryargs = array_merge($queryargs, $typeargs);
            }
        }

        // Category filter.
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

        // Filter tags (including difficulty) in PHP because Moodle tags are flexible.
        $alltags = array_filter(array_map('trim', explode(',', $params['tags'])));
        if (!empty($params['difficulty'])) {
            $alltags[] = trim($params['difficulty']);
        }

        $questions = [];
        foreach ($rows as $row) {
            $qtags = self::get_question_tags((int)$row->id);

            // Apply the tag filter.
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

            // Also search tags for the free-text term.
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
     * Describes the search return values.
     *
     * @return external_single_structure Return definition.
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
     * Describes the parameters for loading categories.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function get_categories_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Current context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Loads all searchable question categories.
     *
     * @param string $scope Search scope: all, course or system.
     * @param int $contextid Current context ID.
     * @return array Categories.
     */
    public static function get_categories(string $scope, int $contextid): array {
        global $DB;

        $params = self::validate_parameters(
            self::get_categories_parameters(),
            ['scope' => $scope, 'contextid' => $contextid]
        );
        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        self::validate_context($context);

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
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
     * Describes the return values for loading categories.
     *
     * @return external_single_structure Return definition.
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
     * Describes the parameters for loading question types.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function get_questiontypes_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'all|course|system', VALUE_DEFAULT, 'all'),
            'contextid' => new external_value(PARAM_INT, 'Current context', VALUE_DEFAULT, 1),
            'source' => new external_value(PARAM_ALPHA, 'existing|installed', VALUE_DEFAULT, 'installed'),
        ]);
    }

    /**
     * Loads the available question types.
     *
     * @param string $scope Search scope: all, course or system.
     * @param int $contextid Current context ID.
     * @param string $source Source: installed or existing.
     * @return array Question types.
     */
    public static function get_questiontypes(string $scope, int $contextid, string $source = 'installed'): array {
        global $DB, $CFG;

        $params = self::validate_parameters(
            self::get_questiontypes_parameters(),
            ['scope' => $scope, 'contextid' => $contextid, 'source' => $source]
        );
        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        self::validate_context($context);

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        $qtypes = [];

        if ($params['source'] === 'installed') {
            // Load all installed qtype plugins.
            $plugintypes = core_component::get_plugin_list('qtype');
            foreach ($plugintypes as $key => $path) {
                // Skip system types.
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
            // Only types actually present in the question bank.
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

        // Sort alphabetically by display name.
        asort($qtypes);

        $result = [];
        foreach ($qtypes as $key => $label) {
            $result[] = ['key' => $key, 'label' => $label];
        }
        return ['qtypes' => $result];
    }

    /**
     * Describes the return values for loading question types.
     *
     * @return external_single_structure Return definition.
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
     * Describes the export parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function export_questions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_value(PARAM_TEXT, 'Comma-separated question IDs'),
            'format' => new external_value(PARAM_ALPHA, 'xml|csv|gift', VALUE_DEFAULT, 'xml'),
            'contextid' => new external_value(PARAM_INT, 'Context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Exports the selected questions in the requested format.
     *
     * @param string $questionids Comma-separated question IDs.
     * @param string $format Target format: xml, csv or gift.
     * @param int $contextid Current context ID.
     * @return array Export file information.
     */
    public static function export_questions(string $questionids, string $format, int $contextid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::export_questions_parameters(), [
            'questionids' => $questionids,
            'format' => $format,
            'contextid' => $contextid,
        ]);

        // Use the system context directly because exports are always site-wide.
        // context::instance_by_id() would throw an exception for contextid=0.
        $context = context_system::instance();
        self::validate_context($context);

        // Export requires a dedicated capability, unavailable to guests.
        if (!has_capability('block/fragenfilter:export', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        $ids = array_filter(array_map('intval', explode(',', $params['questionids'])));
        if (empty($ids)) {
            throw new moodle_exception('noquestionsselected', 'block_fragenfilter');
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

        // Store the file in Moodle's temporary area and return its URL.
        $fs = get_file_storage();
        $sysctx = context_system::instance();
        $fs->delete_area_files($sysctx->id, 'block_fragenfilter', 'export', $params['contextid']);

        $fileinfo = [
            'contextid' => $sysctx->id,
            'component' => 'block_fragenfilter',
            'filearea' => 'export',
            'itemid' => $params['contextid'],
            'filepath' => '/',
            'filename' => $filename,
        ];
        $file = $fs->create_file_from_string($fileinfo, $content);
        $url = moodle_url::make_pluginfile_url(
            $sysctx->id,
            'block_fragenfilter',
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
     * Describes the export return values.
     *
     * @return external_single_structure Return definition.
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
     * Resolves all searchable context IDs for the given scope.
     *
     * Moodle 4.x:  Questions belong to course and system contexts.
     * Moodle 5.0+: Question banks are standalone mod_qbank activities.
     *                           Each question bank has its own CONTEXT_MODULE context.
     *                           Each course also has an automatically migrated
     *                           "shared question bank" as another mod_qbank instance.
     *
     * Scopes:
     * - 'system' : system context only
     * - 'course' : current course context (+ its mod_qbank contexts in Moodle 5)
     * - 'all'    : system + all courses + all mod_qbank contexts (Moodle 5)
     *
     * @param string $scope Search scope: all, course or system.
     * @param int $contextid Current context ID.
     * @return array Context IDs.
     */
    private static function get_searchable_contextids(string $scope, int $contextid): array {
        global $DB, $CFG;

        $sysctx = context_system::instance();
        $ismoodle5 = (int)$CFG->version >= 2024100700; // Corresponds to Moodle 5.0.

        if ($scope === 'system') {
            return [$sysctx->id];
        }

        if ($scope === 'course') {
            $ctx = $contextid > 0
                ? (context::instance_by_id($contextid, IGNORE_MISSING) ?? $sysctx)
                : $sysctx;
            $coursectx = $ctx->get_course_context(false);
            $ids = $coursectx ? [$coursectx->id, $sysctx->id] : [$sysctx->id];

            // Moodle 5+: include the course's mod_qbank contexts.
            if ($ismoodle5 && $coursectx) {
                $qbankctxids = self::get_qbank_module_contextids($coursectx->instanceid);
                $ids = array_unique(array_merge($ids, $qbankctxids));
            }
            return $ids;
        }

        // The all scope includes the system context and all course contexts.
        $coursectxids = $DB->get_fieldset_sql(
            "SELECT id FROM {context} WHERE contextlevel = :lvl",
            ['lvl' => CONTEXT_COURSE]
        );
        $ids = array_merge([$sysctx->id], $coursectxids);

        // Moodle 5+: include all mod_qbank module contexts site-wide.
        if ($ismoodle5) {
            $qbankctxids = self::get_qbank_module_contextids(null);
            $ids = array_unique(array_merge($ids, $qbankctxids));
        }

        return $ids;
    }

    /**
     * Returns all CONTEXT_MODULE contexts belonging to mod_qbank instances.
     * $courseid = null  → site-wide
     * $courseid = int   → this course only
     *
     * Moodle 5.0+ stores question banks as mod_qbank activities.
     * Question categories belong to the module context (contextlevel = 70).
     *
     * @param int|null $courseid Course ID or null for all courses.
     * @return array Context IDs.
     */
    private static function get_qbank_module_contextids(?int $courseid): array {
        global $DB;

        // Mod_qbank only exists in Moodle 5+; check whether the module is available.
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
     * Loads a question's tags.
     *
     * @param int $questionid Question ID.
     * @return array Tags.
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
     * Returns a readable label for a category's context.
     * For mod_qbank instances (Moodle 5), the actual question bank
     * name is read from the qbank instance table.
     *
     * @param int $contextlevel Context level.
     * @param int $instanceid Context instance ID.
     * @return string Readable label.
     */
    private static function context_label(int $contextlevel, int $instanceid): string {
        global $DB;
        switch ($contextlevel) {
            case CONTEXT_SYSTEM:
                return get_string('coresystem', 'block_fragenfilter');

            case CONTEXT_COURSECAT:
                $name = $DB->get_field('course_categories', 'name', ['id' => $instanceid]);
                return $name ? 'Kategorie: ' . $name : 'Kategorie #' . $instanceid;

            case CONTEXT_COURSE:
                $name = $DB->get_field('course', 'fullname', ['id' => $instanceid]);
                return $name ?: 'Kurs #' . $instanceid;

            case CONTEXT_MODULE:
                // Load the course module.
                $cm = $DB->get_record('course_modules', ['id' => $instanceid], 'id,course,module,instance');
                if (!$cm) {
                    return 'Modul #' . $instanceid;
                }

                $coursename = $DB->get_field('course', 'shortname', ['id' => $cm->course]) ?: 'Kurs';

                // Determine the module type.
                $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);

                if ($modname === 'qbank') {
                    // Read the question bank instance name from mod_qbank.
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

                // Fallback: the course name is sufficient.
                return $coursename;

            default:
                return 'Kontext #' . $instanceid;
        }
    }

    /**
     * Exports Moodle XML through the native qformat_xml engine.
     *
     * qformat_xml::exportprocess() expects questions in the old stdClass format
     * (as read from the DB), not question_definition objects from load_question().
     * get_question_options() loads answers, hints and subquestions (multianswer)
     * directly into the stdClass, as required for export.
     *
     * @param array $ids Question IDs.
     * @return string Export file contents.
     */
    private static function export_moodle_xml(array $ids): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        // Load questions as raw DB records (stdClass, not question_definition).
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

        // Get_question_options() populates each question with answers, hints,
        // and, for qtype_multianswer, subquestions as stdClass objects.
        // qtype must explicitly be a string because qformat_xml internally
        // calls (string)$q->qtype, which throws a PHP exception for objects.
        $sysctx = context_system::instance();
        $questions = [];
        foreach ($rows as $q) {
            $q->qtype = (string)$q->qtype; // Ensure qtype is a string.
            $q->category = (int)$q->category;
            get_question_options($q); // Populate $q with answers and hints.
            // Also normalise multianswer subquestions.
            if ($q->qtype === 'multianswer' && !empty($q->options->questions)) {
                foreach ($q->options->questions as $sub) {
                    if (is_object($sub)) {
                        $sub->qtype = (string)($sub->qtype ?? 'shortanswer');
                    }
                }
            }
            $questions[] = $q;
        }

        // Call writequestion() for each question instead of exportprocess().
        // exportprocess() reads category contexts from the DB and internally calls
        // context::instance_by_id(), which throws an exception for invalid context IDs.
        // Calling writequestion() per question avoids that code path
        // entirely.
        $qformat = new qformat_xml();
        $qformat->setContexts([$sysctx]);
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);

        $xmlparts = [];
        foreach ($questions as $q) {
            try {
                $part = $qformat->writequestion($q);
                if ($part !== false && $part !== '') {
                    $xmlparts[] = $part;
                }
            } catch (Exception $e) {
                // Skip unsupported question types.
                debugging(
                    'XML export skipped question ' . $q->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n"
            . implode('', $xmlparts)
            . "</quiz>\n";

        return $content;
    }

    /**
     * Exports CSV: ID, name, type, category, tags.
     * RFC 4180-compliant quoting (escape double quotes).
     *
     * @param array $ids Question IDs.
     * @return string Export file contents.
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
     * Exports GIFT through Moodle's native qformat_gift engine.
     * Uses raw DB records (stdClass), like the XML export,
     * because qformat_gift::writequestion() expects stdClass, not question_definition.
     *
     * @param array $ids Question IDs.
     * @return string Export file contents.
     */
    private static function export_gift(array $ids): string {
        global $CFG, $DB;

        $giftformatfile = $CFG->dirroot . '/question/format/gift/format.php';

        if (file_exists($giftformatfile)) {
            require_once($CFG->libdir . '/questionlib.php');
            require_once($CFG->dirroot . '/question/format.php');
            require_once($giftformatfile);

            // Load raw DB records (stdClass), just like the XML export.
            [$insql, $args] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'qid');
            $rows = $DB->get_records_sql(
                "SELECT q.*, qc.id AS category
                   FROM {question} q
                   JOIN {question_versions} qv      ON qv.questionid        = q.id
                   JOIN {question_bank_entries} qbe  ON qbe.id              = qv.questionbankentryid
                   JOIN {question_categories} qc     ON qc.id               = qbe.questioncategoryid
                  WHERE q.id $insql
                    AND q.parent = 0",
                $args
            );

            if (!empty($rows)) {
                $qformat = new qformat_gift();
                $qformat->setContexts([context_system::instance()]);
                $qformat->setCattofile(false);
                $qformat->setContexttofile(false);

                $output = '// GIFT Export – ' . date('Y-m-d H:i:s') . "\n\n";
                foreach ($rows as $q) {
                    $q->qtype = (string)$q->qtype;
                    try {
                        get_question_options($q);
                        // Normalise multianswer subquestions.
                        if ($q->qtype === 'multianswer' && !empty($q->options->questions)) {
                            foreach ($q->options->questions as $sub) {
                                if (is_object($sub)) {
                                    $sub->qtype = (string)($sub->qtype ?? 'shortanswer');
                                }
                            }
                        }
                        $line = $qformat->writequestion($q);
                        if ($line) {
                            $output .= $line . "\n";
                        }
                    } catch (Exception $e) {
                        // Skip unsupported question types (e.g. ordering).
                        debugging(
                            'GIFT export skipped question ' . $q->id . ': ' . $e->getMessage(),
                            DEBUG_DEVELOPER
                        );
                    }
                }
                return $output;
            }
        }

        // Fallback: plain text when qformat_gift is unavailable.
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

    // ---------------------------------------------------------------
    // Check_capabilities: check the current user's capabilities.

    /**
     * Parameters for check_capabilities.
     */
    public static function check_capabilities_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Returns the logged-in user's current capabilities.
     * Called via AJAX after pool login so JS can show or hide
     * the export buttons accordingly.
     *
     * @param int $contextid Context ID.
     * @return array Capabilities as a boolean map.
     */
    public static function check_capabilities(int $contextid): array {
        $params  = self::validate_parameters(
            self::check_capabilities_parameters(),
            ['contextid' => $contextid]
        );

        // Use the system context because context::instance_by_id() would throw
        // an exception for contextid=0 or an invalid value.
        $sysctx = context_system::instance();
        self::validate_context($sysctx);

        $fmtconfig = get_config('block_fragenfilter', 'exportformats');
        if (is_array($fmtconfig)) {
            $formats = implode(',', array_keys(array_filter($fmtconfig)));
        } else {
            $formats = 'xml,csv,gift';
        }
        if (empty($formats)) {
            $formats = 'xml,csv,gift';
        }

        return [
            'canexport'     => has_capability('block/fragenfilter:export', $sysctx),
            'canpreview'    => isloggedin() && !isguestuser(),
            'isloggedin'    => isloggedin() && !isguestuser(),
            'exportformats' => $formats,
        ];
    }

    /**
     * Return structure for check_capabilities.
     */
    public static function check_capabilities_returns(): external_single_structure {
        return new external_single_structure([
            'canexport'     => new external_value(PARAM_BOOL),
            'canpreview'    => new external_value(PARAM_BOOL),
            'isloggedin'    => new external_value(PARAM_BOOL),
            'exportformats' => new external_value(PARAM_TEXT, 'Comma-separated export formats', VALUE_DEFAULT, 'xml,csv,gift'),
        ]);
    }

    // ---------------------------------------------------------------
    // Rate_question: save a star rating (logged-in users and guests).

    /**
     * Parameters for rate_question.
     */
    public static function rate_question_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid'  => new external_value(PARAM_INT, 'Question ID'),
            'rating'      => new external_value(PARAM_INT, 'Rating 1-5'),
            'sessionhash' => new external_value(PARAM_ALPHANUMEXT, 'Session hash for guests', VALUE_DEFAULT, ''),
            'contextid'   => new external_value(PARAM_INT, 'Context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Saves a question's star rating.
     *
     * Logged-in users are identified by userid;
     * guests use a client-side session hash.
     * Each user or guest can submit one rating per question
     * and update it later.
     *
     * @param int    $questionid  Question ID.
     * @param int    $rating      Rating from 1 to 5.
     * @param string $sessionhash Session hash for guests.
     * @param int    $contextid   Current context.
     * @return array Current average, count and own rating.
     */
    public static function rate_question(
        int $questionid,
        int $rating,
        string $sessionhash,
        int $contextid
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::rate_question_parameters(), [
            'questionid'  => $questionid,
            'rating'      => $rating,
            'sessionhash' => $sessionhash,
            'contextid'   => $contextid,
        ]);

        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        self::validate_context($context);

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        $r   = max(1, min(5, (int)$params['rating']));
        $qid = (int)$params['questionid'];

        if (!$DB->record_exists('question', ['id' => $qid])) {
            throw new moodle_exception('invalidquestion', 'block_fragenfilter');
        }

        $now     = time();
        $isguest = isguestuser() || !isloggedin();

        if ($isguest) {
            $hash = clean_param($params['sessionhash'], PARAM_ALPHANUMEXT);
            if (empty($hash)) {
                throw new moodle_exception('nosessionhash', 'block_fragenfilter');
            }
            $existing = $DB->get_record(
                'block_fragenfilter_ratings',
                ['questionid' => $qid, 'sessionhash' => $hash, 'userid' => null]
            );
            if ($existing) {
                $existing->rating      = $r;
                $existing->timemodified = $now;
                $DB->update_record('block_fragenfilter_ratings', $existing);
            } else {
                $DB->insert_record('block_fragenfilter_ratings', (object)[
                    'questionid'   => $qid,
                    'userid'       => null,
                    'sessionhash'  => $hash,
                    'rating'       => $r,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        } else {
            $existing = $DB->get_record(
                'block_fragenfilter_ratings',
                ['questionid' => $qid, 'userid' => (int)$USER->id]
            );
            if ($existing) {
                $existing->rating      = $r;
                $existing->timemodified = $now;
                $DB->update_record('block_fragenfilter_ratings', $existing);
            } else {
                $DB->insert_record('block_fragenfilter_ratings', (object)[
                    'questionid'   => $qid,
                    'userid'       => (int)$USER->id,
                    'sessionhash'  => null,
                    'rating'       => $r,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        return self::compute_rating(
            $qid,
            $isguest ? null : (int)$USER->id,
            $isguest ? $params['sessionhash'] : ''
        );
    }

    /**
     * Return structure for rate_question.
     */
    public static function rate_question_returns(): external_single_structure {
        return new external_single_structure([
            'average'  => new external_value(PARAM_FLOAT, 'Average'),
            'count'    => new external_value(PARAM_INT, 'Number of ratings'),
            'myrating' => new external_value(PARAM_INT, 'Own rating (0 = none)'),
        ]);
    }

    // ---------------------------------------------------------------
    // Get_ratings: load averages for a list of questions.

    /**
     * Parameters for get_ratings.
     */
    public static function get_ratings_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionids' => new external_value(PARAM_TEXT, 'Comma-separated question IDs'),
            'sessionhash' => new external_value(PARAM_ALPHANUMEXT, 'Session hash for guests', VALUE_DEFAULT, ''),
            'contextid'   => new external_value(PARAM_INT, 'Context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Loads rating data for multiple questions.
     *
     * @param string $questionids Comma-separated question IDs.
     * @param string $sessionhash Session hash for guests.
     * @param int    $contextid   Current context.
     * @return array Average, count and own rating for each question.
     */
    public static function get_ratings(
        string $questionids,
        string $sessionhash,
        int $contextid
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_ratings_parameters(), [
            'questionids' => $questionids,
            'sessionhash' => $sessionhash,
            'contextid'   => $contextid,
        ]);

        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        self::validate_context($context);

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        $ids = array_filter(array_map('intval', explode(',', $params['questionids'])));
        if (empty($ids)) {
            return ['ratings' => []];
        }

        $isguest = isguestuser() || !isloggedin();
        $userid  = $isguest ? null : (int)$USER->id;
        $hash    = clean_param($params['sessionhash'], PARAM_ALPHANUMEXT);

        [$insql, $args] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'qid');

        // Average and count per question.
        $avgs = $DB->get_records_sql(
            "SELECT questionid,
                    AVG(rating) AS average,
                    COUNT(*)    AS cnt
               FROM {block_fragenfilter_ratings}
              WHERE questionid $insql
           GROUP BY questionid",
            $args
        );

        // The current user's or guest's own rating.
        $myratings = [];
        if ($userid) {
            $rows = $DB->get_records_sql(
                "SELECT questionid, rating
                   FROM {block_fragenfilter_ratings}
                  WHERE questionid $insql
                    AND userid = :uid",
                array_merge($args, ['uid' => $userid])
            );
            foreach ($rows as $row) {
                $myratings[(int)$row->questionid] = (int)$row->rating;
            }
        } else if ($hash) {
            $rows = $DB->get_records_sql(
                "SELECT questionid, rating
                   FROM {block_fragenfilter_ratings}
                  WHERE questionid $insql
                    AND sessionhash = :hash
                    AND userid IS NULL",
                array_merge($args, ['hash' => $hash])
            );
            foreach ($rows as $row) {
                $myratings[(int)$row->questionid] = (int)$row->rating;
            }
        }

        $result = [];
        foreach ($ids as $qid) {
            $result[] = [
                'questionid' => $qid,
                'average'    => isset($avgs[$qid]) ? round((float)$avgs[$qid]->average, 1) : 0.0,
                'count'      => isset($avgs[$qid]) ? (int)$avgs[$qid]->cnt : 0,
                'myrating'   => $myratings[$qid] ?? 0,
            ];
        }
        return ['ratings' => $result];
    }

    /**
     * Return structure for get_ratings.
     */
    public static function get_ratings_returns(): external_single_structure {
        return new external_single_structure([
            'ratings' => new external_multiple_structure(
                new external_single_structure([
                    'questionid' => new external_value(PARAM_INT),
                    'average'    => new external_value(PARAM_FLOAT),
                    'count'      => new external_value(PARAM_INT),
                    'myrating'   => new external_value(PARAM_INT),
                ])
            ),
        ]);
    }

    /**
     * Calculates the average and own rating for one question.
     *
     * @param int      $qid    Question ID.
     * @param int|null $userid User ID or null for guests.
     * @param string   $hash   Session hash for guests.
     * @return array Average, count and own rating.
     */
    private static function compute_rating(int $qid, ?int $userid, string $hash): array {
        global $DB;

        $avg  = (float)$DB->get_field_sql(
            "SELECT AVG(rating) FROM {block_fragenfilter_ratings} WHERE questionid = :qid",
            ['qid' => $qid]
        );
        $cnt  = $DB->count_records('block_fragenfilter_ratings', ['questionid' => $qid]);
        $mine = 0;

        if ($userid) {
            $mine = (int)($DB->get_field(
                'block_fragenfilter_ratings',
                'rating',
                ['questionid' => $qid, 'userid' => $userid]
            ) ?: 0);
        } else if ($hash) {
            $mine = (int)($DB->get_field(
                'block_fragenfilter_ratings',
                'rating',
                ['questionid' => $qid, 'sessionhash' => $hash, 'userid' => null]
            ) ?: 0);
        }

        return [
            'average'  => round($avg, 1),
            'count'    => (int)$cnt,
            'myrating' => $mine,
        ];
    }

    // ---------------------------------------------------------------
    // Get_question_preview: load question text and answer options.

    /**
     * Parameters for get_question_preview.
     */
    public static function get_question_preview_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'contextid'  => new external_value(PARAM_INT, 'Context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Loads question text and answer options for the preview.
     *
     * Returns the question text as sanitised HTML and
     * answer options for multiple-choice questions. Available to all
     * users with block/fragenfilter:view, including guests.
     *
     * @param int $questionid Question ID.
     * @param int $contextid  Current context.
     * @return array Question text, type and answer options.
     */
    public static function get_question_preview(int $questionid, int $contextid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::get_question_preview_parameters(), [
            'questionid' => $questionid,
            'contextid'  => $contextid,
        ]);

        $context = $params['contextid'] > 0
            ? context::instance_by_id($params['contextid'], IGNORE_MISSING) ?? context_system::instance()
            : context_system::instance();
        self::validate_context($context);

        if (!has_capability('block/fragenfilter:view', context_system::instance())) {
            throw new moodle_exception('nopermission', 'block_fragenfilter');
        }

        $qid = (int)$params['questionid'];

        // Load the question's base data.
        $q = $DB->get_record('question', ['id' => $qid, 'parent' => 0], '*', MUST_EXIST);

        // Sanitise question text (HTML allowed, but only safe tags).
        require_once($CFG->libdir . '/weblib.php');
        $sysctx     = context_system::instance();
        $questiontext = format_text($q->questiontext, $q->questiontextformat, [
            'context' => $sysctx,
            'noclean' => false,
            'filter'  => false,
        ]);

        // Remove general feedback fields to avoid spoilers.
        $result = [
            'questionid'   => $qid,
            'qtype'        => $q->qtype,
            'name'         => $q->name,
            'questiontext' => $questiontext,
            'answers'      => [],
            'hinttext'     => '',
        ];

        // Load answer options according to the question type.
        switch ($q->qtype) {
            case 'multichoice':
                $answers = $DB->get_records(
                    'question_answers',
                    ['question' => $qid],
                    'fraction DESC, id ASC'
                );
                foreach ($answers as $a) {
                    $result['answers'][] = [
                        'text'    => format_text($a->answer, $a->answerformat, [
                            'context' => $sysctx,
                            'noclean' => false,
                            'filter'  => false,
                        ]),
                        'correct' => ($a->fraction > 0),
                    ];
                }
                break;

            case 'truefalse':
                $answers = $DB->get_records(
                    'question_answers',
                    ['question' => $qid],
                    'id ASC'
                );
                foreach ($answers as $a) {
                    $result['answers'][] = [
                        'text'    => $a->answer === 'True' ? get_string('true', 'qtype_truefalse')
                                                            : get_string('false', 'qtype_truefalse'),
                        'correct' => ($a->fraction > 0),
                    ];
                }
                break;

            case 'shortanswer':
                // Show only the first model answer to avoid a full spoiler.
                $answer = $DB->get_record_sql(
                    "SELECT answer FROM {question_answers}
                      WHERE question = :qid
                   ORDER BY fraction DESC, id ASC",
                    ['qid' => $qid],
                    IGNORE_MULTIPLE
                );
                if ($answer) {
                    $result['hinttext'] = get_string('preview_expectedanswer', 'block_fragenfilter')
                        . ': ' . s($answer->answer);
                }
                break;

            case 'numerical':
                $answer = $DB->get_record_sql(
                    "SELECT answer, tolerance FROM {question_answers}
                      WHERE question = :qid
                   ORDER BY fraction DESC, id ASC",
                    ['qid' => $qid],
                    IGNORE_MULTIPLE
                );
                if ($answer) {
                    $result['hinttext'] = get_string('preview_expectedanswer', 'block_fragenfilter')
                        . ': ' . s($answer->answer)
                        . ($answer->tolerance > 0 ? ' ± ' . s($answer->tolerance) : '');
                }
                break;

            case 'match':
                $subquestions = $DB->get_records(
                    'qtype_match_subquestions',
                    ['questionid' => $qid],
                    'id ASC'
                );
                foreach ($subquestions as $sub) {
                    if (!empty($sub->questiontext)) {
                        $result['answers'][] = [
                            'text'    => s($sub->questiontext) . ' → ' . s($sub->answertext),
                            'correct' => true,
                        ];
                    }
                }
                break;

            default:
                // Show only the question text for all other types.
                break;
        }

        return $result;
    }

    /**
     * Return structure for get_question_preview.
     */
    public static function get_question_preview_returns(): external_single_structure {
        return new external_single_structure([
            'questionid'   => new external_value(PARAM_INT),
            'qtype'        => new external_value(PARAM_ALPHA),
            'name'         => new external_value(PARAM_TEXT),
            'questiontext' => new external_value(PARAM_RAW),
            'answers'      => new external_multiple_structure(
                new external_single_structure([
                    'text'    => new external_value(PARAM_RAW),
                    'correct' => new external_value(PARAM_BOOL),
                ])
            ),
            'hinttext' => new external_value(PARAM_TEXT),
        ]);
    }
}
