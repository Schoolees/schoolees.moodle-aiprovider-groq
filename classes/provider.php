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

namespace aiprovider_groq;

use core_ai\form\action_settings_form;
use Psr\Http\Message\RequestInterface;

/**
 * Groq AI provider.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string[] The actions handled by the chat completions API. */
    private const TEXT_ACTIONS = ['generate_text', 'summarise_text', 'explain_text'];

    /**
     * Get the list of actions that this provider supports.
     *
     * Groq Cloud only exposes text (chat completion) models, so image generation
     * is deliberately not offered here.
     *
     * @return array An array of action class names.
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\summarise_text::class,
            \core_ai\aiactions\explain_text::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * This is a hash of the site id and user id, this means we can determine
     * who made the request but don't pass any personal data to Groq.
     *
     * @param string $userid The user id.
     * @return string The generated user id.
     */
    public function generate_userid(string $userid): string {
        global $CFG;
        return hash('sha256', $CFG->siteidentifier . $userid);
    }

    /**
     * Update a request to add any headers required by the provider.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        $apikey = (string) ($this->config['apikey'] ?? '');

        return $request->withHeader('Authorization', "Bearer {$apikey}");
    }

    /**
     * Get the settings form for a specific action.
     *
     * @param string $action The action class name.
     * @param array $customdata Custom data passed by core.
     * @return action_settings_form|bool A form instance or false if not supported.
     */
    public static function get_action_settings(string $action, array $customdata = []): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;

        if (in_array($actionname, self::TEXT_ACTIONS, true)) {
            return new form\action_generate_text_form(customdata: $customdata);
        }

        return false;
    }

    /**
     * Get the default settings for an action.
     *
     * Without this, a newly created provider instance has no model or endpoint
     * configured until an administrator opens and saves the action settings form.
     *
     * @param string $action The action class name.
     * @return array The default settings for the action.
     */
    public static function get_action_setting_defaults(string $action): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));

        if (!in_array($actionname, self::TEXT_ACTIONS, true)) {
            return [];
        }

        $mform = new form\action_generate_text_form(customdata: [
            'actionname' => $actionname,
            'action' => $action,
            'providername' => 'aiprovider_groq',
        ]);

        return $mform->get_defaults();
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->config['apikey']);
    }
}
