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
 * Scheduled task: daily forum summary for local_ai_forum_assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_forum_assistant\task;

use core\task\scheduled_task;
use local_ai_forum_assistant\forum_processor;
use local_ai_forum_assistant\api\openai_client;

/**
 * Processes daily AI summaries for all forums configured in "daily" mode.
 *
 * The task is idempotent: if it runs more than once per day for the same
 * forum, duplicate responses will not be generated (enforced via the
 * usage table unique constraint on (forumid, usage_date)).
 */
class daily_summary_task extends scheduled_task {
    /**
     * Returns the human-readable name of this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_daily_name', 'local_ai_forum_assistant');
    }

    /**
     * Executes the daily summary task.
     *
     * @return void
     */
    public function execute(): void {
        if (openai_client::is_globally_disabled()) {
            mtrace('[local_ai_forum_assistant] Plugin is globally disabled. Skipping daily summary task.');
            return;
        }

        if (openai_client::is_rate_limited()) {
            mtrace('[local_ai_forum_assistant] OpenAI rate limit is active. Skipping daily summary task.');
            return;
        }

        mtrace('[local_ai_forum_assistant] Starting daily summary task.');
        forum_processor::process_daily_summaries();
        mtrace('[local_ai_forum_assistant] Daily summary task complete.');
    }
}
