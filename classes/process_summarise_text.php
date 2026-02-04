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

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Class process text summarisation.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_summarise_text extends process_generate_text {
    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri((string) $this->get_action_setting('endpoint', 'https://api.groq.com/openai/v1/chat/completions'));
    }

    #[\Override]
    protected function get_model(): string {
        return (string) $this->get_action_setting('model', 'llama-3.1-8b-instant');
    }

    #[\Override]
    protected function get_system_instruction(): string {
        // Start with the configured instruction (or the core default).
        $instruction = (string) $this->get_action_setting('systeminstruction', $this->action::get_system_instruction());

        // Enforce a hard cap on the summarisation length.
        // We keep this in the provider so it applies even if the admin never edits the instruction.
        $wordlimit = 500;
        $instruction .= "\n\nLimit the summary to a maximum of {$wordlimit} words. Write it as a single paragraph (no bullet points, no line breaks).";

        return $instruction;
    }

    #[\Override]
    protected function get_temperature(): string {
        return (string) $this->get_action_setting('temperature', '0.2');
    }


    /**
     * Handle a successful response from the external AI api.
     *
     * For summarisation we enforce output formatting constraints server-side:
     * - Maximum 500 words
     * - Single paragraph (no newlines)
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     */
    protected function handle_api_success(\Psr\Http\Message\ResponseInterface $response): array {
        $result = parent::handle_api_success($response);
        if (empty($result['success']) || empty($result['generatedcontent'])) {
            return $result;
        }

        $content = (string) $result['generatedcontent'];

        // Force single paragraph.
        $content = preg_replace("/\R+/u", " ", $content);
        $content = preg_replace('/\s{2,}/u', ' ', trim($content));

        // Enforce 500-word limit.
        $words = preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($words) && count($words) > 500) {
            $content = implode(' ', array_slice($words, 0, 500));
        }

        $result['generatedcontent'] = $content;
        return $result;
    }
}
