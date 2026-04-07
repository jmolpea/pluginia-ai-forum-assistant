# Forum IA Assistant (`local_forumia`)

A Moodle local plugin that adds an AI-powered assistant to course forums. The assistant monitors student posts and responds automatically via an OpenAI-compatible API, acting as a real Moodle user selected by the course administrator.

---

## Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.5.x or later |
| PHP | 8.2 or later |
| mod_forum | Must be installed (Moodle core) |
| OpenAI API | Valid API key with access to the configured model |

---

## Installation

1. Copy the `forumia` folder to `/path/to/moodle/local/forumia/`.
2. Log in to Moodle as a site administrator.
3. Navigate to **Site administration > Notifications** and complete the database upgrade.
4. Navigate to **Site administration > Plugins > Local > Forum IA Assistant** and enter your OpenAI API key.
5. Create the bot user (see below) and enter its username or ID in the *Default Site-wide IA User* setting.

---

## Creating the Bot User

The IA assistant posts responses as a real Moodle user. This user should be created manually before configuring the plugin:

1. Go to **Site administration > Users > Add a new user**.
2. Create a user with a clear name (e.g. *Forum AI Assistant*) and a secure password.
3. **Do not assign any site-level admin or course editing roles** to this user.
4. The user only needs to be *enrolled* in the courses where the assistant is active (enrolment happens automatically if the bot posts in a forum, but it is good practice to enrol them explicitly with the *student* role or a custom role with no elevated permissions).
5. Note the username or user ID and enter it in the plugin settings.

> **Security note:** The bot user must not have `moodle/site:config`, `moodle/course:update`, or any other privileged capability. Treat this account like any other limited-access service account.

---

## Capabilities and Roles

| Capability | Default roles | Description |
|---|---|---|
| `local/forumia:managesettings` | editingteacher, manager | Allows configuring the IA assistant for a specific forum. |
| `local/forumia:viewdisclaimer` | All logged-in users | Allows seeing the IA disclaimer appended to bot responses. |

Assign `local/forumia:managesettings` to any role that should be able to enable or configure the assistant in a forum.

---

## Global Settings

Located at **Site administration > Plugins > Local > Forum IA Assistant**.

| Setting | Description |
|---|---|
| OpenAI API Key | Your secret API key. Stored encrypted, never logged. |
| OpenAI Model | Model to use: gpt-4o (default), gpt-4o-mini, gpt-4-turbo, gpt-3.5-turbo. |
| API Endpoint | Change only if using a compatible proxy. Default: OpenAI's official endpoint. |
| Site-wide Rate Limit | Optional cap on total API calls per hour across the entire site. |
| Per-user Rate Limit | Optional cap on API calls per user per hour. |
| Daily Summary Hour | Hour of day (server time) when the daily summary task runs. |
| Default Site IA User | The Moodle user that acts as the assistant when no forum-specific user is set. |

---

## Per-Forum Settings

Accessible via **Forum > Settings > IA Assistant** (visible to teachers and managers).

| Setting | Description |
|---|---|
| Enable IA Assistant | Master switch for this forum. |
| IA Response User | The Moodle user that will post IA responses. Falls back to the site default if empty. |
| Response Mode | *Immediate* — reply to each student post; *Daily* — send a consolidated summary once per day. |
| Prompt (immediate) | System prompt sent to OpenAI for each student post. |
| Prompt (daily) | System prompt for the daily consolidated summary. |
| Disclaimer | Text appended to every IA response. Leave empty to disable. |
| Daily Request Limit | Maximum OpenAI API calls per day for this forum. |

---

## Response Modes

### Immediate Mode

Triggered by the `\mod_forum\event\post_created` event. The assistant replies to each student post individually. Only posts authored by students (not teachers or managers) in student-initiated threads are processed.

### Daily Mode

Triggered by the `Forum IA Assistant – Daily Summary` scheduled task at the configured hour. The assistant collects all student posts from the last 24 hours, anonymises them, and generates a single consolidated reply posted in the most recent active thread.

---

## Privacy and Data Protection

- **No personal data is stored** by this plugin beyond what is required for configuration (user IDs for the bot account).
- Student names, email addresses, and user IDs are **never sent to OpenAI**.
- In daily mode, students are replaced by sequential anonymous labels (*Student 1*, *Student 2*, …) before the content leaves Moodle.
- In immediate mode, only the anonymised post content and the forum description (stripped of HTML) are sent.
- The `{local_forumia_usage}` table stores only aggregate daily request counts — no message content or user identities.
- This plugin implements `\core_privacy\local\metadata\null_provider` and declares it stores no personal data.

---

## Security Notes

- The OpenAI API key is stored using Moodle's `get_config`/`set_config` mechanism and is **never** written to logs, error messages, or stack traces.
- All user inputs are sanitised with `clean_param()` before use.
- All database queries use Moodle's parameterised query API — no string concatenation in SQL.
- Session key (`sesskey`) is verified on all form submissions.
- Access to the forum settings page requires `local/forumia:managesettings` checked against the course context.
- If the API key is rejected by OpenAI (HTTP 401/403), the plugin **disables itself globally** and sends an internal Moodle notification to all site administrators.
- A loop-detection guard prevents the bot from responding to its own posts.

---

## Known Limitations

- The bot user must be enrolled in the relevant courses manually (or via a cohort/enrolment rule) before it can post.
- The daily summary posts to the **most recent thread** that had student activity in the last 24 hours. If there are multiple active discussions, only one receives the consolidated summary.
- Site-wide and per-user rate limits apply to the *number of API calls* and are tracked in Moodle config — they are approximate and may not be perfectly accurate under heavy concurrent load.
- The plugin does not support streaming responses or function-calling features of the OpenAI API.
- Changing the scheduled task hour in global settings does not automatically update the cron record — an admin must also update the task schedule in **Site administration > Server > Scheduled tasks**.
