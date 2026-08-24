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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
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
class process_generate_text extends abstract_processor {
    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri((string) $this->get_action_setting('endpoint', self::DEFAULT_ENDPOINT));
    }

    #[\Override]
    protected function get_model(): string {
        return (string) $this->get_action_setting('model', self::DEFAULT_MODEL);
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        // Create the user object.
        $userobj = new \stdClass();
        $userobj->role = 'user';
        $userobj->content = $this->action->get_configuration('prompttext');

        // Create the request object.
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->user = $userid;
        $requestobj->temperature = (float) $this->get_temperature();

        // If there is a system string available, use it.
        $systeminstruction = $this->get_system_instruction();
        if ($systeminstruction !== '') {
            $systemobj = new \stdClass();
            $systemobj->role = 'system';
            $systemobj->content = $systeminstruction;
            $requestobj->messages = [$systemobj, $userobj];
        } else {
            $requestobj->messages = [$userobj];
        }

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode($requestobj, JSON_THROW_ON_ERROR),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());

        $content = $bodyobj->choices[0]->message->content ?? null;
        if (!is_object($bodyobj) || !is_string($content) || trim($content) === '') {
            return [
                'success' => false,
                'errorcode' => -1,
                'errormessage' => 'Unexpected response from external AI service',
            ];
        }

        // Every field below is optional in the OpenAI-compatible response shape and Groq
        // does not always return all of them, so read each one defensively.
        return [
            'success' => true,
            'id' => $bodyobj->id ?? null,
            'fingerprint' => $bodyobj->system_fingerprint ?? null,
            'generatedcontent' => $content,
            'finishreason' => $bodyobj->choices[0]->finish_reason ?? null,
            'prompttokens' => $bodyobj->usage->prompt_tokens ?? null,
            'completiontokens' => $bodyobj->usage->completion_tokens ?? null,
            'model' => $bodyobj->model ?? $this->get_model(),
        ];
    }
}
