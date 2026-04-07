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
 * Restore plugin for local_ai_forum_assistant.
 *
 * Reads the AI Forum Assistant configuration from a backup and inserts (or
 * updates) the corresponding row in {local_aifa_config} using the
 * new forum ID assigned during restore.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 AI Forum Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class for local_ai_forum_assistant.
 */
class restore_local_ai_forum_assistant_plugin extends restore_local_plugin {
    /**
     * Tells the restore API that this plugin hooks into the 'forum' module.
     *
     * @return string
     */
    protected function get_activity_name() {
        return 'forum';
    }

    /** @var stdClass|null Raw config data saved during parsing, written to DB in after_restore_module(). */
    private $aifaconfig = null;

    /**
     * Returns the restore path elements for local_ai_forum_assistant.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element('aifa_config', $this->get_pathfor('/aifa_config')),
        ];
    }

    /**
     * Stores the aifa_config element for later processing.
     *
     * @param array|object $data Raw data from the backup XML.
     */
    public function process_aifa_config($data) {
        $this->aifaconfig = (object)$data;
    }

    /**
     * Writes the stored aifa_config to the database.
     *
     * Called after all restore steps for this activity have completed,
     * so the forum record exists and get_task()->get_activityid() returns
     * its new instance ID.
     */
    public function after_restore_module() {
        global $DB;

        if ($this->aifaconfig === null) {
            return; // Nothing to restore (forum had no AI config in the backup).
        }

        $data = clone $this->aifaconfig;

        // The forum has been fully restored by now; get the new instance ID.
        $data->forumid = $this->get_task()->get_activityid();
        if (!$data->forumid) {
            return;
        }

        // Remap the bot user; disable the assistant if the user does not exist
        // in the target site to avoid broken API calls.
        $newbotuserid = $this->get_mappingid('user', $data->bot_userid);
        if ($newbotuserid) {
            $data->bot_userid = $newbotuserid;
        } else {
            $data->bot_userid = 0;
            $data->enabled   = 0;
        }

        // FREE VERSION: always enforce free-tier values on restore.
        $data->response_mode         = 'immediate';
        $data->delay_response        = 0;
        $data->daily_prompt          = '';
        $data->disclaimer            = '';
        $data->max_requests_user_day = 0;

        // Remove the original PK — the DB will assign a new one.
        unset($data->id);

        // Upsert: update if a config row already exists for this forum.
        if ($existing = $DB->get_record('local_aifa_config', ['forumid' => $data->forumid])) {
            $data->id = $existing->id;
            $DB->update_record('local_aifa_config', $data);
        } else {
            $DB->insert_record('local_aifa_config', $data);
        }
    }
}
