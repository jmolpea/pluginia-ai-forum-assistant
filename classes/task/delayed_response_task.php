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
 * Adhoc task that publishes a delayed IA response.
 *
 * Queued by forum_processor when delay_response is enabled on a forum.
 * The task is scheduled 1 hour after the triggering student post and
 * calls the same processing logic as the immediate-mode observer path.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_forum_assistant\task;

use local_ai_forum_assistant\forum_processor;

/**
 * Adhoc task for delayed IA responses in immediate mode.
 */
class delayed_response_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name shown in the Moodle admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_delayed_response_name', 'local_ai_forum_assistant');
    }

    /**
     * Executes the delayed IA response.
     *
     * Reads forumid, postid and authorid from the custom data stored when
     * the task was queued, then delegates to forum_processor.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();

        if (empty($data->forumid) || empty($data->postid) || empty($data->authorid)) {
            // Defensive check: malformed task data, nothing to do.
            mtrace('[local_ai_forum_assistant] delayed_response_task: missing custom data, skipping.');
            return;
        }

        forum_processor::process_new_post(
            (int) $data->forumid,
            (int) $data->postid,
            (int) $data->authorid,
            true   // $fromtask = true — bypass the delay-queueing step.
        );
    }
}
