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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_ai_forum_assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 AI Forum Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps for local_ai_forum_assistant.
 *
 * @param  int  $oldversion Version number of the currently installed plugin.
 * @return bool             True on success.
 */
function xmldb_local_ai_forum_assistant_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // No upgrade steps yet — initial release of AI Forum Assistant.

    return true;
}
