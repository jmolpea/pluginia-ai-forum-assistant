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
 * Privacy provider for local_ai_forum_assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_forum_assistant\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for local_ai_forum_assistant.
 *
 * This plugin does not store personal data in its own tables.
 * However, it transmits forum post content to the OpenAI API (a third-party
 * external service) to generate automated responses.
 *
 * Data sent to OpenAI:
 * - In immediate mode: the plain-text body of each student forum post, plus
 *   the forum description (intro). No names, emails or user IDs are included.
 * - In daily mode: the plain-text bodies of all student posts from the last
 *   24 hours, labelled with anonymous sequential identifiers (Student 1,
 *   Student 2 …). No names, emails or user IDs are included.
 *
 * Administrators should ensure their institution's data-processing agreements
 * cover the transfer of (potentially personal) forum content to OpenAI and
 * communicate this to students via the site's privacy notice.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata describing the external data transfer to OpenAI.
     *
     * @param  collection $collection The metadata collection to populate.
     * @return collection             The populated collection.
     */
    public static function get_metadata(collection $collection): collection {

        // Declare the external transfer to the OpenAI API.
        // The key maps to a lang string in local_ai_forum_assistant.
        $collection->add_external_location_link(
            'openai_api',
            [
                'forum_post_content' => 'privacy:metadata:openai_api:forum_post_content',
            ],
            'privacy:metadata:openai_api'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     *
     * This plugin does not store personal data, so an empty contextlist is returned.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * This plugin does not store personal data, so nothing is exported.
     *
     * @param approved_contextlist $contextlist The list of approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No personal data stored — nothing to export.
    }

    /**
     * Delete all user data for the specified context.
     *
     * This plugin does not store personal data, so nothing is deleted.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No personal data stored — nothing to delete.
    }

    /**
     * Delete all user data for the specified users within the specified context.
     *
     * This plugin does not store personal data, so nothing is deleted.
     *
     * @param approved_contextlist $contextlist The list of approved contexts and users.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No personal data stored — nothing to delete.
    }

    /**
     * Get the list of users who have data within the specified context.
     *
     * This plugin does not store personal data, so the userlist is not modified.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        // No personal data stored — no users to report.
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * This plugin does not store personal data, so nothing is deleted.
     *
     * @param approved_userlist $userlist The userlist to delete data for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // No personal data stored — nothing to delete.
    }
}
