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

use core_ai\aiactions;
use core_ai\form\action_settings_form;
use core_ai\rate_limiter;
use Psr\Http\Message\RequestInterface;

/**
 * Class provider.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Get a provider instance setting, falling back to legacy plugin config.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function get_provider_setting(string $key, mixed $default = null): mixed {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        $legacy = get_config('aiprovider_groq', $key);
        return $legacy !== false && $legacy !== null ? $legacy : $default;
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * This is a hash of the site id and user id,
     * this means we can determine who made the request
     * but don't pass any personal data to OpenAI.
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
        $apikey = (string) $this->get_provider_setting('apikey', '');
        $orgid = (string) $this->get_provider_setting('orgid', '');

        $request = $request->withAddedHeader('Authorization', "Bearer {$apikey}");
        if (!empty($orgid)) {
            $request = $request->withAddedHeader('OpenAI-Organization', $orgid);
        }
        return $request;
    }

    /**
     * Check if the request is allowed by the rate limiter.
     *
     * @param aiactions\base $action The action to check.
     * @return array|bool True on success, array of error details on failure.
     */
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = \core\di::get(rate_limiter::class);
        $component = \core\component::get_component_from_classname(get_class($this));

        // Check the user rate limit.
        $enableuserratelimit = (bool) $this->get_provider_setting('enableuserratelimit', false);
        $userratelimit = (int) $this->get_provider_setting('userratelimit', 0);
        if ($enableuserratelimit) {
            if (!$ratelimiter->check_user_rate_limit(
                component: $component,
                ratelimit: $userratelimit,
                userid: $action->get_configuration('userid')
            )) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'User rate limit exceeded',
                ];
            }
        }

        // Check the global rate limit.
        $enableglobalratelimit = (bool) $this->get_provider_setting('enableglobalratelimit', false);
        $globalratelimit = (int) $this->get_provider_setting('globalratelimit', 0);
        if ($enableglobalratelimit) {
            if (!$ratelimiter->check_global_rate_limit(
                component: $component,
                ratelimit: $globalratelimit
            )) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'Global rate limit exceeded',
                ];
            }
        }

        return true;
    }

    /**
     * Get the settings form for a specific action (Moodle 5+).
     *
     * @param string $action The action class name.
     * @param array $customdata Custom data passed by core.
     * @return action_settings_form|bool A form instance or false if not supported.
     */
    public static function get_action_settings(string $action, array $customdata = []): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;

        if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
            return new \aiprovider_groq\form\action_generate_text_form(null, $customdata);
        }

        if ($actionname === 'generate_image') {
            return new \aiprovider_groq\form\action_generate_image_form(null, $customdata);
        }

        return false;
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->get_provider_setting('apikey', ''));
    }
}
