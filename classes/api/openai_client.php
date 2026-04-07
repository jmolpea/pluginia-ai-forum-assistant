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
 * OpenAI HTTP client for local_ai_forum_assistant.
 *
 * @package   local_ai_forum_assistant
 * @copyright 2025 Forum IA Assistant
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_forum_assistant\api;

use core\http_client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use moodle_exception;

/**
 * Wrapper around the OpenAI chat completions API.
 *
 * Security notes:
 * - The API key is never written to logs, error messages or stack traces.
 * - All errors are normalised before being passed to mtrace/debugging.
 */
class openai_client {
    /** @var string OpenAI API endpoint URL. */
    private string $endpoint;

    /** @var string OpenAI model identifier. */
    private string $model;

    /** @var string Masked placeholder used in safe log messages. */
    private const APIKEY_MASK = '[REDACTED]';

    /**
     * Allowed hostname suffixes for the API endpoint.
     *
     * Only HTTPS endpoints whose host exactly matches or is a subdomain of one
     * of these values are accepted. This prevents SSRF attacks where a
     * compromised admin config redirects requests to internal network services.
     *
     * Supported providers:
     * - OpenAI direct:  api.openai.com
     * - Azure OpenAI:   <resource>.openai.azure.com
     *
     * @var string[]
     */
    private const ALLOWED_ENDPOINT_HOSTS = [
        'api.openai.com',
        'openai.azure.com',
    ];

    /**
     * Constructor.
     *
     * Reads global plugin configuration. Throws if the API key is absent.
     *
     * @throws moodle_exception If no API key is configured.
     */
    public function __construct() {
        $apikey = get_config('local_ai_forum_assistant', 'apikey');
        if (empty($apikey)) {
            throw new moodle_exception('error_noapikey', 'local_ai_forum_assistant');
        }
        // Store the key in a private property — never expose it outside this class.
        $this->apikey   = $apikey;
        $this->endpoint = get_config('local_ai_forum_assistant', 'endpoint') ?: 'https://api.openai.com/v1/chat/completions';
        // FREE VERSION: model is fixed — upgrade to choose your preferred model.
        $this->model    = 'gpt-4.1-nano';

        // Validate the endpoint against the allowed-host whitelist.
        // Done here (constructor) so the check runs for every call path.
        $this->validate_endpoint($this->endpoint);
    }

    /** @var string OpenAI API key — never logged or exposed externally. */
    private string $apikey;

    /**
     * Validates the configured API endpoint against the allowed-host whitelist.
     *
     * Rules enforced:
     * 1. Scheme MUST be https — plain http is rejected to prevent credential
     *    interception and to block most SSRF vectors against internal HTTP APIs.
     * 2. Host MUST NOT be an IP address — numeric IPs almost always indicate
     *    an SSRF attempt targeting cloud metadata services or internal hosts.
     * 3. Host MUST exactly match or be a subdomain of one of ALLOWED_ENDPOINT_HOSTS.
     *
     * @param  string $url The endpoint URL to validate.
     * @return void
     * @throws moodle_exception If the endpoint fails any validation rule.
     */
    private function validate_endpoint(string $url): void {
        $parsed = parse_url($url);

        // Rule 1: HTTPS only.
        if (empty($parsed['scheme']) || strtolower($parsed['scheme']) !== 'https') {
            throw new moodle_exception('error_endpoint_https', 'local_ai_forum_assistant');
        }

        $host = strtolower($parsed['host'] ?? '');
        if ($host === '') {
            throw new moodle_exception('error_endpoint_blocked', 'local_ai_forum_assistant');
        }

        // Rule 2: Reject raw IP addresses (IPv4 and IPv6).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new moodle_exception('error_endpoint_blocked', 'local_ai_forum_assistant');
        }
        // IPv6 addresses may appear as [::1] in a URL — strip brackets and re-check.
        $hostnobrk = trim($host, '[]');
        if (filter_var($hostnobrk, FILTER_VALIDATE_IP) !== false) {
            throw new moodle_exception('error_endpoint_blocked', 'local_ai_forum_assistant');
        }

        // Rule 3: Host must be in the whitelist (exact match or subdomain).
        foreach (self::ALLOWED_ENDPOINT_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return; // Endpoint is safe.
            }
        }

        throw new moodle_exception('error_endpoint_blocked', 'local_ai_forum_assistant');
    }

    /**
     * Sends a chat completion request to OpenAI and returns the text response.
     *
     * @param  string $systemprompt The system-role message.
     * @param  string $usermessage  The user-role message.
     * @return string|null          The text content of the first choice, or null on failure.
     */
    public function chat(string $systemprompt, string $usermessage): ?string {
        $payload = [
            'model'      => $this->model,
            'messages'   => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $usermessage],
            ],
            'max_tokens'  => 800,
            'temperature' => 0.7,
        ];

        try {
            $client   = new http_client(['timeout' => 30]);
            $response = $client->post($this->endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apikey,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $payload,
            ]);

            $statuscode = $response->getStatusCode();
            $body       = (string) $response->getBody();
            $data       = json_decode($body, true);

            if ($statuscode === 200 && isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            // Unexpected 2xx without the expected structure.
            $this->log_error('Unexpected OpenAI response structure. Status: ' . $statuscode);
            return null;
        } catch (RequestException $e) {
            $this->handle_request_exception($e);
            return null;
        } catch (ConnectException $e) {
            $this->log_error('OpenAI connection timeout or network error.');
            return null;
        } catch (\Throwable $e) {
            // Catch-all: ensure the API key never leaks in unexpected exception messages.
            $safe = $this->sanitise_exception_message($e->getMessage());
            $this->log_error('Unexpected error calling OpenAI: ' . $safe);
            return null;
        }
    }

    /**
     * Handles Guzzle HTTP-level exceptions, dispatching on status code.
     *
     * @param RequestException $e The caught exception.
     * @return void
     */
    private function handle_request_exception(RequestException $e): void {
        if (!$e->hasResponse()) {
            $this->log_error('OpenAI request failed with no HTTP response (network error or timeout).');
            return;
        }

        $statuscode = $e->getResponse()->getStatusCode();

        switch (true) {
            case $statuscode === 401 || $statuscode === 403:
                $this->log_error('OpenAI API key invalid or unauthorised (HTTP ' . $statuscode . ').');
                $this->disable_plugin_globally();
                $this->notify_admin_api_error();
                break;

            case $statuscode === 429:
                $this->log_error('OpenAI rate limit reached (HTTP 429). The assistant will pause for one hour.');
                $this->set_rate_limit_pause();
                break;

            case $statuscode === 400:
                // Bad request — most commonly an invalid model name or malformed payload.
                // Log the response body (API key is never in the response, only in request
                // headers, so the body is safe to log after sanitisation).
                $this->log_error(
                    'OpenAI bad request (HTTP 400). Likely cause: invalid model name or request parameter. '
                    . 'Detail: ' . $this->safe_response_body($e)
                );
                break;

            case $statuscode >= 500:
                $this->log_error('OpenAI server error (HTTP ' . $statuscode . '). Not retrying.');
                break;

            default:
                $this->log_error(
                    'OpenAI HTTP error: ' . $statuscode . '. Detail: ' . $this->safe_response_body($e)
                );
        }
    }

    /**
     * Extracts and sanitises the OpenAI response body from a RequestException.
     *
     * The response body never contains the API key (keys are only sent in
     * request headers), but we run it through sanitise_exception_message()
     * as an extra safety measure before writing to the log.
     *
     * @param  RequestException $e The caught exception.
     * @return string              Sanitised response body, or a placeholder if unavailable.
     */
    private function safe_response_body(RequestException $e): string {
        if (!$e->hasResponse()) {
            return '(no response body)';
        }
        $body = (string) $e->getResponse()->getBody();
        // Truncate to 500 chars to keep log entries readable.
        if (mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 500) . '…';
        }
        return $this->sanitise_exception_message($body);
    }

    /**
     * Disables the plugin globally by setting a config flag.
     *
     * A site administrator must re-enable it after fixing the API key.
     *
     * @return void
     */
    private function disable_plugin_globally(): void {
        set_config('globally_disabled', 1, 'local_ai_forum_assistant');
        $this->log_error(get_string('error_apiunauthorized', 'local_ai_forum_assistant'));
    }

    /**
     * Sends an internal Moodle notification to all site administrators about the API error.
     *
     * @return void
     */
    private function notify_admin_api_error(): void {
        global $DB;

        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $subject = get_string('pluginname', 'local_ai_forum_assistant') . ': ' .
                   get_string('error_apiunauthorized', 'local_ai_forum_assistant');
        $body    = get_string('error_apiunauthorized', 'local_ai_forum_assistant') .
                   ' Please review your API key in: Site administration > Plugins > Local > Forum IA Assistant.';

        $supportuser = \core_user::get_support_user();
        foreach ($admins as $admin) {
            $message                      = new \core\message\message();
            $message->component           = 'local_ai_forum_assistant';
            $message->name                = 'api_error';
            $message->userfrom            = $supportuser;
            $message->userto              = $admin;
            $message->subject             = $subject;
            $message->fullmessage         = $body;
            $message->fullmessageformat   = FORMAT_PLAIN;
            $message->fullmessagehtml     = '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>';
            $message->smallmessage        = $subject;
            $message->notification        = 1;
            message_send($message);
        }
    }

    /**
     * Stores a timestamp so the processor can detect the rate-limit pause period.
     *
     * @return void
     */
    private function set_rate_limit_pause(): void {
        set_config('ratelimit_until', time() + HOURSECS, 'local_ai_forum_assistant');
    }

    /**
     * Writes a safe error message to the Moodle debug log.
     *
     * The API key is never included in log messages.
     *
     * @param string $message Human-readable error description (must not contain the API key).
     * @return void
     */
    private function log_error(string $message): void {
        debugging('[local_ai_forum_assistant] ' . $message, DEBUG_NORMAL);
    }

    /**
     * Replaces any occurrence of the API key in an exception message with a safe mask.
     *
     * @param  string $message Raw exception message.
     * @return string          Sanitised message safe for logging.
     */
    private function sanitise_exception_message(string $message): string {
        if (!empty($this->apikey)) {
            $message = str_replace($this->apikey, self::APIKEY_MASK, $message);
        }
        return $message;
    }

    /**
     * Returns true if the plugin is currently in a global rate-limit pause.
     *
     * @return bool
     */
    public static function is_rate_limited(): bool {
        $until = (int) get_config('local_ai_forum_assistant', 'ratelimit_until');
        return $until > time();
    }

    /**
     * Returns true if the plugin has been globally disabled due to an API auth error.
     *
     * @return bool
     */
    public static function is_globally_disabled(): bool {
        return (bool) get_config('local_ai_forum_assistant', 'globally_disabled');
    }
}
