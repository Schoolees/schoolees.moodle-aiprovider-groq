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

use core\http_client;
use core_ai\aiactions\responses\response_base;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class process text generation.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /**
     * Get the (short) action name (e.g. generate_text).
     *
     * @return string
     */
    protected function get_action_name(): string {
        $action = get_class($this->action);
        return substr($action, (strrpos($action, '\\') + 1));
    }

    /**
     * Get the action settings array for the current action.
     *
     * @return array
     */
    protected function get_action_settings(): array {
        $actionname = $this->get_action_name();
        $actionconfig = $this->provider->actionconfig ?? [];

        if (isset($actionconfig[$actionname]['settings']) && is_array($actionconfig[$actionname]['settings'])) {
            return $actionconfig[$actionname]['settings'];
        }

        if (isset($actionconfig['settings']) && is_array($actionconfig['settings'])) {
            return $actionconfig['settings'];
        }

        return [];
    }

    /**
     * Read an action-level setting (Moodle 5+), falling back to legacy plugin config.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function get_action_setting(string $key, mixed $default = null): mixed {
        $settings = $this->get_action_settings();
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $actionname = $this->get_action_name();
        $legacy = get_config('aiprovider_groq', "action_{$actionname}_{$key}");
        return $legacy !== false && $legacy !== null ? $legacy : $default;
    }

    /**
     * Get the endpoint URI.
     *
     * @return UriInterface
     */
    abstract protected function get_endpoint(): UriInterface;

    /**
     * Get the name of the model to use.
     *
     * @return string
     */
    abstract protected function get_model(): string;

    /**
     * Get the temperature to use for generation.
     *
     * @return string
     */
    protected function get_temperature(): string {
        return (string) $this->get_action_setting("temperature", "0.2");
    }


    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        return $this->action::get_system_instruction();
    }

    /**
     * Create the request object to send to the OpenAI API.
     *
     * This object contains all the required parameters for the request.
     *
     *
     *
     * @param string $userid The user id.
     * @return RequestInterface The request object to send to the OpenAI API.
     */
    abstract protected function create_request_object(
        string $userid,
    ): RequestInterface;

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    #[\Override]
    protected function query_ai_api(): array {
        try {
            $request = $this->create_request_object(
                userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
            );
            $request = $this->provider->add_authentication_headers($request);

            $client = \core\di::get(http_client::class);
            // Call the external AI service.
            $response = $client->send($request, [
                'base_uri' => $this->get_endpoint(),
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            // Handle any exceptions.
            return [
                'success' => false,
                'errorcode' => $e->getCode() ?: -1,
                'errormessage' => $e->getMessage() ?: 'Request to external AI service failed',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errorcode' => -1,
                'errormessage' => $e->getMessage() ?: 'Unexpected error calling external AI service',
            ];
        }

        // Double-check the response codes, in case of a non 200 that didn't throw an error.
        $status = $response->getStatusCode();
        if ($status === 200) {
            try {
                return $this->handle_api_success($response);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'errorcode' => -1,
                    'errormessage' => $e->getMessage() ?: 'Unexpected error processing AI response',
                ];
            }
        } else {
            try {
                return $this->handle_api_error($response);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'errorcode' => $status ?: -1,
                    'errormessage' => $e->getMessage() ?: 'Unexpected error processing AI error response',
                ];
            }
        }
    }

    /**
     * Handle an error from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The error response.
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $responsearr = [
            'success' => false,
            'errorcode' => $response->getStatusCode(),
        ];

        $status = $response->getStatusCode();
        $reason = (string) $response->getReasonPhrase();
        $body = (string) $response->getBody()->getContents();
        $bodyarr = json_decode($body, true);

        $message = '';
        if (is_array($bodyarr)) {
            // OpenAI/Groq-style: {"error": {"message": "..."}}.
            if (!empty($bodyarr['error']['message']) && is_string($bodyarr['error']['message'])) {
                $message = $bodyarr['error']['message'];
            } else if (!empty($bodyarr['message']) && is_string($bodyarr['message'])) {
                $message = $bodyarr['message'];
            } else if (!empty($bodyarr['error']) && is_string($bodyarr['error'])) {
                $message = $bodyarr['error'];
            }
        }

        if ($message === '') {
            // Fallback to reason phrase or raw body.
            $message = $reason !== '' ? $reason : trim($body);
        }

        if ($message === '') {
            $message = "External AI service returned HTTP {$status}";
        }

        $responsearr['errormessage'] = $message;

        return $responsearr;
    }
}
