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

namespace block_questionfilter;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The installed hook registration preserves delegation to Kursfilter.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    public function test_registered_after_config_preserves_kursfilter_delegation(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $manager = \core\hook\manager::get_instance();
        $hook = new \core\hook\after_config();
        $callbacks = iterator_to_array($manager->getListenersForEvent($hook));
        $this->assertContains(hook_callbacks::class . '::after_config', $callbacks);

        $regular = $this->getDataGenerator()->create_user();
        $pool = $this->getDataGenerator()->create_user(['username' => 'kursfilter_pool001']);
        foreach ([null, guest_user(), $regular, $pool] as $user) {
            $this->setUser($user);
            $userid = $USER->id;
            $CFG->messaging = true;

            $manager->dispatch($hook);

            $this->assertSame($userid, $USER->id);
            $this->assertTrue($CFG->messaging, 'Kursfilter owns pool restrictions when installed.');
        }
    }
}
