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
 * Block class for block_questionfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @author     Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Question bank filter block class.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_questionfilter extends block_base {
    /**
     * Initialises the block and sets its title.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_questionfilter');
    }

    /**
     * Defines the page types on which the block can be used.
     *
     * @return array Page types with permission flags.
     */
    public function applicable_formats(): array {
        return [
            'site-index' => true,
            'course-view' => true,
            'my' => true,
        ];
    }

    /**
     * Indicates that the block has global settings.
     *
     * @return bool Always true.
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Indicates whether multiple instances are allowed on a page.
     *
     * @return bool Always false.
     */
    public function instance_allow_multiple(): bool {
        return false;
    }



    /**
     * Generates the block content.
     *
     * @return stdClass Content object with rendered text.
     */
    public function get_content(): stdClass {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // The dedicated view capability can be granted to guests.
        if (!has_capability('block/questionfilter:view', context_system::instance())) {
            $this->content->text = get_string('nopermission', 'block_questionfilter');
            return $this->content;
        }

        // Check export permission and pass it to JavaScript.
        // Recalculate each time so pool accounts see the correct buttons after login.
        $canexport = has_capability('block/questionfilter:export', context_system::instance());

        // Load the AMD module.
        $this->page->requires->js_call_amd('block_questionfilter/filter', 'init', [
            [
                'blockid' => (int)$this->instance->id,
                'contextid' => (int)$this->page->context->id,
                'searchscope' => get_config('block_questionfilter', 'searchscope') ?: 'all',
                'exportformats' => (function () {
                    $fmt = get_config('block_questionfilter', 'exportformats');
                    return is_array($fmt)
                        ? (implode(',', array_keys(array_filter($fmt))) ?: 'xml,csv,gift')
                        : ($fmt ?: 'xml,csv,gift');
                })(),
                'qtypessource' => get_config('block_questionfilter', 'questiontypes_source') ?: 'installed',
                'wwwroot' => $CFG->wwwroot,
                'canexport' => $canexport,
                'isloggedin' => (isloggedin() && !isguestuser()),
            ],
        ]);

        $renderer = $this->page->get_renderer('block_questionfilter');
        $this->content->text = $renderer->render_block((int)$this->instance->id, $canexport);

        return $this->content;
    }
}
