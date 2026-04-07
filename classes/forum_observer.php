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
 * Event observer for local_ai_forum_assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_forum_assistant;

use local_ai_forum_assistant\api\openai_client;

/**
 * Listens for forum events and delegates processing to {@see forum_processor}.
 *
 * The observer is intentionally lightweight: it performs only the minimal
 * pre-checks required to decide whether to hand off to the processor.
 * A top-level try/catch ensures that any failure in this plugin NEVER
 * interrupts the normal Moodle forum experience for end users.
 */
class forum_observer {
    /**
     * Handles the \mod_forum\event\post_created event.
     *
     * @param  \mod_forum\event\post_created $event The Moodle event object.
     * @return void
     */
    public static function post_created(\mod_forum\event\post_created $event): void {
        try {
            // Fast-exit checks before loading any further data.
            if (openai_client::is_globally_disabled()) {
                return;
            }

            if (openai_client::is_rate_limited()) {
                return;
            }

            $forumid = (int) $event->other['forumid'];
            $postid  = (int) $event->objectid;

            // Delegate all further processing to the processor.
            forum_processor::process_new_post($forumid, $postid, $event->userid);
        } catch (\Throwable $e) {
            // Log the error safely and continue — do NOT re-throw.
            debugging(
                '[local_ai_forum_assistant] Unhandled exception in forum_observer::post_created: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
