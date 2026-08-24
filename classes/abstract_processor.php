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
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Base class for the Groq action processors.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /** @var string The default Groq OpenAI-compatible chat completions endpoint. */
    public const DEFAULT_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    /** @var string The default model used when an action has not been configured. */
    public const DEFAULT_MODEL = 'llama-3.1-8b-instant';

    /** @var string The default sampling temperature. */
    public const DEFAULT_TEMPERATURE = '0.2';

    /** @var int Maximum number of characters kept from an unrecognised error body. */
    private const ERROR_MESSAGE_MAX_LENGTH = 500;

    /**
     * Get the settings stored against the action this processor handles.
     *
     * Moodle 5 keys the provider instance action config by the fully qualified
     * action class name, so that is what we look up here.
     *
     * @return array The action settings, or an empty array when the action has never been configured.
     */
    protected function get_action_settings(): array {
        $settings = $this->provider->actionconfig[$this->action::class]['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    /**
     * Read a single action setting, falling back to a default when it is absent or blank.
     *
     * @param string $key The setting name.
     * @param mixed $default The value to use when the setting has not been configured.
     * @return mixed The configured value, or the default.
     */
    protected function get_action_setting(string $key, mixed $default = null): mixed {
        $settings = $this->get_action_settings();

        if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
            return $settings[$key];
        }

        return $default;
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
        return (string) $this->get_action_setting('temperature', self::DEFAULT_TEMPERATURE);
    }

    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        return (string) $this->get_action_setting('systeminstruction', $this->action::get_system_instruction());
    }

    /**
     * Create the request object to send to the Groq API.
     *
     * This object contains all the required parameters for the request.
     *
     * @param string $userid The user id.
     * @return RequestInterface The request object to send to the Groq API.
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
        try {
            if ($status === 200) {
                return $this->handle_api_success($response);
            }
            return $this->handle_api_error($response);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errorcode' => $status === 200 ? -1 : $status,
                'errormessage' => $e->getMessage() ?: 'Unexpected error processing the AI service response',
            ];
        }
    }

    /**
     * Handle an error from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The error response.
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody()->getContents();
        $bodyarr = json_decode($body, true);

        $message = '';
        if (is_array($bodyarr)) {
            // Groq mirrors the OpenAI error shape: {"error": {"message": "..."}}.
            if (!empty($bodyarr['error']['message']) && is_string($bodyarr['error']['message'])) {
                $message = $bodyarr['error']['message'];
            } else if (!empty($bodyarr['message']) && is_string($bodyarr['message'])) {
                $message = $bodyarr['message'];
            } else if (!empty($bodyarr['error']) && is_string($bodyarr['error'])) {
                $message = $bodyarr['error'];
            }
        }

        if ($message === '') {
            // Fall back to the reason phrase, then to the raw body. The body can be a full HTML
            // error page from an intermediate proxy, so keep only a useful prefix of it.
            $reason = (string) $response->getReasonPhrase();
            $message = $reason !== '' ? $reason : \core_text::substr(trim($body), 0, self::ERROR_MESSAGE_MAX_LENGTH);
        }

        if ($message === '') {
            $message = "External AI service returned HTTP {$status}";
        }

        return [
            'success' => false,
            'errorcode' => $status,
            'errormessage' => $message,
        ];
    }
}
