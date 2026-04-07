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
 * Global admin settings page for local_ai_forum_assistant.
 *
 * Accessible at: Site administration > Plugins > Local > Forum IA Assistant
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ai_forum_assistant',
        get_string('pluginname', 'local_ai_forum_assistant')
    );

    // Heading.
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/heading',
        get_string('settings_heading', 'local_ai_forum_assistant'),
        get_string('settings_heading_desc', 'local_ai_forum_assistant')
    ));

    // 1. OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_ai_forum_assistant/apikey',
        get_string('settings_apikey', 'local_ai_forum_assistant'),
        get_string('settings_apikey_desc', 'local_ai_forum_assistant'),
        ''
    ));

    // 2. OpenAI Model (FREE: fixed to gpt-4.1-nano).
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/model_locked_heading',
        get_string('settings_model', 'local_ai_forum_assistant'),
        '<div class="text-muted">'
        . '<span class="fa fa-lock" aria-hidden="true"></span> '
        . 'GPT-4.1 nano &mdash; ' . get_string('free_locked_value', 'local_ai_forum_assistant')
        . '</div>'
    ));

    // 3. API Endpoint.
    $settings->add(new admin_setting_configtext(
        'local_ai_forum_assistant/endpoint',
        get_string('settings_endpoint', 'local_ai_forum_assistant'),
        get_string('settings_endpoint_desc', 'local_ai_forum_assistant'),
        'https://api.openai.com/v1/chat/completions',
        PARAM_URL
    ));

    // 4. Site-wide rate limit.
    $settings->add(new admin_setting_configcheckbox(
        'local_ai_forum_assistant/siteratelimit_enabled',
        get_string('settings_siteratelimit', 'local_ai_forum_assistant'),
        get_string('settings_siteratelimit_desc', 'local_ai_forum_assistant'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_ai_forum_assistant/siteratelimit_max',
        get_string('settings_siteratelimit_max', 'local_ai_forum_assistant'),
        '',
        100,
        PARAM_INT
    ));

    // 5. Per-user rate limit (FREE: locked).
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/userratelimit_locked_heading',
        get_string('settings_userratelimit', 'local_ai_forum_assistant'),
        '<div class="text-muted">'
        . '<span class="fa fa-lock" aria-hidden="true"></span> '
        . get_string('free_locked_premium', 'local_ai_forum_assistant')
        . '</div>'
    ));
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/userratelimit_max_locked_heading',
        get_string('settings_userratelimit_max', 'local_ai_forum_assistant'),
        '<div class="text-muted">'
        . '<span class="fa fa-lock" aria-hidden="true"></span> '
        . get_string('free_locked_premium', 'local_ai_forum_assistant')
        . '</div>'
    ));

    // 6. Daily summary hour (FREE: locked, daily mode is not available).
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/dailyhour_locked_heading',
        get_string('settings_dailyhour', 'local_ai_forum_assistant'),
        '<div class="text-muted">'
        . '<span class="fa fa-lock" aria-hidden="true"></span> '
        . get_string('free_locked_premium', 'local_ai_forum_assistant')
        . '</div>'
    ));

    // 7. Default site IA user.
    // PARAM_NOTAGS strips any HTML/script tags while preserving all valid
    // Moodle username characters (letters, digits, dots, hyphens, @, etc.).
    // PARAM_RAW_TRIMMED was too permissive — it would allow HTML injection
    // into the stored value. The field is further sanitised at read time
    // via clean_param($setting, PARAM_USERNAME) in resolve_default_bot_userid().
    $settings->add(new admin_setting_configtext(
        'local_ai_forum_assistant/defaultbot',
        get_string('settings_defaultbot', 'local_ai_forum_assistant'),
        get_string('settings_defaultbot_desc', 'local_ai_forum_assistant'),
        '',
        PARAM_NOTAGS
    ));

    // Marketing block.
    $settings->add(new admin_setting_heading(
        'local_ai_forum_assistant/marketing_heading',
        '',
        '<div class="alert alert-info" style="margin-top:1.5em;">'
        . get_string('free_marketing_global', 'local_ai_forum_assistant')
        . '</div>'
    ));

    // Register under the Local plugins category.
    $ADMIN->add('localplugins', $settings);

}
