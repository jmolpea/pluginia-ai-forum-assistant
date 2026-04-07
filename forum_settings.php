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
 * Per-forum AI Assistant settings page for local_ai_forum_assistant.
 *
 * Accessible via the forum's Settings navigation: Settings > AI Assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

// Parameters and access control.
$forumid = required_param('forumid', PARAM_INT);
$cmid    = required_param('cmid', PARAM_INT);

$forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
$cm    = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);

// Verify that the supplied forumid belongs to the supplied cmid to prevent IDOR.
if ((int)$cm->instance !== $forumid) {
    throw new moodle_exception('error_invalidforum', 'local_ai_forum_assistant');
}

// Require_login sets PAGE context to module context (level 70).
require_login($cm->course, false, $cm);

$coursecontext = context_course::instance($cm->course);
require_capability('local/ai_forum_assistant:managesettings', $coursecontext);

// Page setup — use the module context; downgrading context level is not allowed.
$modulecontext = context_module::instance($cmid);

$PAGE->set_url('/local/ai_forum_assistant/forum_settings.php', ['forumid' => $forumid, 'cmid' => $cmid]);
$PAGE->set_context($modulecontext);
$PAGE->set_title(get_string('forum_settings_title', 'local_ai_forum_assistant'));
$PAGE->set_heading($forum->name . ' – ' . get_string('forum_settings_title', 'local_ai_forum_assistant'));
$PAGE->set_pagelayout('admin');

// Inline moodleform definition.

/**
 * Form for configuring the AI assistant on a specific forum.
 */
class local_ai_forum_assistant_forum_settings_form extends moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform   = $this->_form;
        $forumid = $this->_customdata['forumid'];
        $cmid    = $this->_customdata['cmid'];
        $course  = $this->_customdata['course'];

        $mform->addElement('hidden', 'forumid', $forumid);
        $mform->setType('forumid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // 1. Enable toggle.
        $mform->addElement('advcheckbox', 'enabled', get_string('forum_enabled', 'local_ai_forum_assistant'), '');
        $mform->setDefault('enabled', 0);

        // 2. Bot user selector.
        $candidateuserids = [];

        // Site default bot.
        $defaultbotsetting = get_config('local_ai_forum_assistant', 'defaultbot');
        if (!empty($defaultbotsetting)) {
            if (ctype_digit((string) $defaultbotsetting)) {
                $candidateuserids[] = (int) $defaultbotsetting;
            } else {
                $botuser = $DB->get_record('user', ['username' => clean_param($defaultbotsetting, PARAM_USERNAME)]);
                if ($botuser) {
                    $candidateuserids[] = (int) $botuser->id;
                }
            }
        }

        // Teachers and managers in the course.
        $context      = context_course::instance($course->id);
        $managerroles = ['editingteacher', 'teacher', 'manager', 'coursecreator'];
        foreach ($managerroles as $roleshortname) {
            $role = $DB->get_record('role', ['shortname' => $roleshortname]);
            if (!$role) {
                continue;
            }
            $users = get_role_users($role->id, $context, false, 'u.id', 'u.id');
            foreach ($users as $u) {
                $candidateuserids[] = (int) $u->id;
            }
        }

        $candidateuserids = array_unique($candidateuserids);

        // Build select options from the candidate user IDs.
        $useroptions = ['' => get_string('choosedots')];
        if (!empty($candidateuserids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($candidateuserids, SQL_PARAMS_NAMED);
            $candidateusers = $DB->get_records_select(
                'user',
                "id $insql AND deleted = 0 AND suspended = 0",
                $inparams,
                'lastname ASC, firstname ASC',
                'id, firstname, lastname, username, firstnamephonetic, lastnamephonetic, middlename, alternatename'
            );
            foreach ($candidateusers as $u) {
                $useroptions[$u->id] = fullname($u) . ' (' . $u->username . ')';
            }
        }

        $mform->addElement('select', 'bot_userid', get_string('forum_botuser', 'local_ai_forum_assistant'), $useroptions);
        $mform->setType('bot_userid', PARAM_INT);

        // 3. Response mode (FREE: immediate only).
        // The daily mode option is shown as locked — available in the full version.
        // A hidden field carries the forced value so it is always 'immediate'
        // regardless of anything submitted.
        $mform->addElement('hidden', 'response_mode', 'immediate');
        $mform->setType('response_mode', PARAM_ALPHA);
        $immediatedisplay = '<div>'
            . '<div><input type="radio" disabled checked> '
            . get_string('forum_mode_immediate', 'local_ai_forum_assistant') . '</div>'
            . '<div class="text-muted mt-1"><input type="radio" disabled>'
            . ' <span class="fa fa-lock" aria-hidden="true"></span> '
            . get_string('forum_mode_daily', 'local_ai_forum_assistant')
            . ' &mdash; ' . get_string('free_locked_premium', 'local_ai_forum_assistant') . '</span></div>'
            . '</div>';
        $mform->addElement(
            'static',
            'response_mode_display',
            get_string('forum_mode', 'local_ai_forum_assistant'),
            $immediatedisplay
        );

        // 3b. Delay response (PREMIUM, locked).
        $mform->addElement('hidden', 'delay_response', 0);
        $mform->setType('delay_response', PARAM_INT);
        $mform->addElement(
            'static',
            'delay_response_display',
            get_string('forum_delay_response', 'local_ai_forum_assistant'),
            '<span class="text-muted">'
            . '<span class="fa fa-lock" aria-hidden="true"></span> '
            . get_string('free_locked_premium', 'local_ai_forum_assistant')
            . '</span>'
        );

        // 4. Prompt for immediate mode.
        $placeholder = get_string('forum_prompt_immediate_placeholder', 'local_ai_forum_assistant');
        $mform->addElement(
            'textarea',
            'immediate_prompt',
            get_string('forum_prompt_immediate', 'local_ai_forum_assistant'),
            ['rows' => 6, 'cols' => 70, 'placeholder' => $placeholder]
        );
        $mform->setType('immediate_prompt', PARAM_TEXT);
        $mform->addElement(
            'static',
            'immediate_prompt_note',
            '',
            get_string('forum_prompt_immediate_desc', 'local_ai_forum_assistant')
        );

        // 5. Prompt for daily mode (PREMIUM, locked).
        $mform->addElement('hidden', 'daily_prompt', '');
        $mform->setType('daily_prompt', PARAM_TEXT);
        $mform->addElement(
            'static',
            'daily_prompt_display',
            get_string('forum_prompt_daily', 'local_ai_forum_assistant'),
            '<span class="text-muted">'
            . '<span class="fa fa-lock" aria-hidden="true"></span> '
            . get_string('free_locked_premium', 'local_ai_forum_assistant')
            . '</span>'
        );

        // 6. Disclaimer (PREMIUM, locked).
        $mform->addElement('hidden', 'disclaimer', '');
        $mform->setType('disclaimer', PARAM_TEXT);
        $mform->addElement(
            'static',
            'disclaimer_display',
            get_string('forum_disclaimer', 'local_ai_forum_assistant'),
            '<span class="text-muted">'
            . '<span class="fa fa-lock" aria-hidden="true"></span> '
            . get_string('free_locked_premium', 'local_ai_forum_assistant')
            . '</span>'
        );

        // 7. Daily request limit (forum).
        $globalmax = (int) get_config('local_ai_forum_assistant', 'siteratelimit_max') ?: 50;
        $mform->addElement(
            'text',
            'max_requests_day',
            get_string('forum_maxrequests', 'local_ai_forum_assistant'),
            ['size' => 6]
        );
        $mform->setType('max_requests_day', PARAM_INT);
        $mform->setDefault('max_requests_day', $globalmax);
        $mform->addElement(
            'static',
            'max_requests_day_note',
            '',
            get_string('forum_maxrequests_desc', 'local_ai_forum_assistant')
        );

        // 8. Daily request limit per user (PREMIUM, locked).
        $mform->addElement('hidden', 'max_requests_user_day', 0);
        $mform->setType('max_requests_user_day', PARAM_INT);
        $mform->addElement(
            'static',
            'max_requests_user_day_display',
            get_string('forum_maxrequests_user', 'local_ai_forum_assistant'),
            '<span class="text-muted">'
            . '<span class="fa fa-lock" aria-hidden="true"></span> '
            . get_string('free_locked_premium', 'local_ai_forum_assistant')
            . '</span>'
        );

        // Marketing block.
        $mform->addElement(
            'html',
            '<div class="alert alert-info" style="margin-top:1.5em;">'
            . get_string('free_marketing_forum', 'local_ai_forum_assistant')
            . '</div>'
        );

        // Submit button.
        $this->add_action_buttons(true, get_string('forum_save', 'local_ai_forum_assistant'));
    }

    /**
     * Validates the submitted form data.
     *
     * @param  array $data  Submitted form data.
     * @param  array $files Uploaded files (not used).
     * @return array        Associative array of field => error message.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        if (!empty($data['enabled'])) {
            if (empty($data['bot_userid'])) {
                $errors['bot_userid'] = get_string('error_nobotuser', 'local_ai_forum_assistant', $data['forumid']);
            } else {
                $user = $DB->get_record('user', ['id' => $data['bot_userid'], 'deleted' => 0, 'suspended' => 0]);
                if (!$user) {
                    $errors['bot_userid'] = get_string('error_nobotuser', 'local_ai_forum_assistant', $data['forumid']);
                }
            }

            if (isset($data['max_requests_day']) && (int) $data['max_requests_day'] < 1) {
                $errors['max_requests_day'] = get_string('required');
            }
        }

        return $errors;
    }
}

// Load existing configuration.
$existingconfig = $DB->get_record('local_aifa_config', ['forumid' => $forumid]);
$course         = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$mform = new local_ai_forum_assistant_forum_settings_form(
    new moodle_url('/local/ai_forum_assistant/forum_settings.php'),
    ['forumid' => $forumid, 'cmid' => $cmid, 'course' => $course]
);

if ($existingconfig) {
    $mform->set_data($existingconfig);
}

// Handle form submission.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/forum/view.php', ['id' => $cmid]));
} else if ($data = $mform->get_data()) {
    $record                   = new stdClass();
    $record->forumid          = clean_param($data->forumid, PARAM_INT);
    $record->enabled          = clean_param($data->enabled ?? 0, PARAM_INT);
    $record->bot_userid       = clean_param($data->bot_userid, PARAM_INT);
    $record->immediate_prompt = clean_param($data->immediate_prompt ?? '', PARAM_TEXT);
    $record->max_requests_day = clean_param($data->max_requests_day ?? 50, PARAM_INT);
    $record->timemodified     = time();

    // FREE VERSION: premium fields are always forced to their free-tier values
    // regardless of what was submitted. This is enforced server-side so that
    // manipulating hidden fields or raw HTTP requests has no effect.
    $record->response_mode         = 'immediate';
    $record->delay_response        = 0;
    $record->daily_prompt          = '';
    $record->disclaimer            = '';
    $record->max_requests_user_day = 0;

    if ($existingconfig) {
        $record->id = $existingconfig->id;
        $DB->update_record('local_aifa_config', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_aifa_config', $record);
    }

    redirect(
        new moodle_url('/local/ai_forum_assistant/forum_settings.php', ['forumid' => $forumid, 'cmid' => $cmid]),
        get_string('forum_saved', 'local_ai_forum_assistant'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forum_settings_title', 'local_ai_forum_assistant'));
$mform->display();
echo $OUTPUT->footer();
