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

use Psr\Http\Message\ResponseInterface;

/**
 * Class process text summarisation.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_summarise_text extends process_generate_text {
    /** @var int The default maximum number of words in a summary. Zero means no limit. */
    public const DEFAULT_WORD_LIMIT = 500;

    /** @var int Whether summaries are collapsed to a single paragraph by default. */
    public const DEFAULT_SINGLE_PARAGRAPH = 1;

    /**
     * Get the configured maximum number of words for a summary.
     *
     * @return int The word limit, or 0 when summaries are not limited.
     */
    protected function get_word_limit(): int {
        return max(0, (int) $this->get_action_setting('wordlimit', self::DEFAULT_WORD_LIMIT));
    }

    /**
     * Whether the summary should be collapsed into a single paragraph.
     *
     * @return bool
     */
    protected function is_single_paragraph(): bool {
        return (bool) $this->get_action_setting('singleparagraph', self::DEFAULT_SINGLE_PARAGRAPH);
    }

    #[\Override]
    protected function get_system_instruction(): string {
        $instruction = parent::get_system_instruction();

        // Ask the model for the same constraints we enforce below, so that in the common
        // case it produces a compliant summary rather than one we have to truncate.
        $constraints = [];
        if ($wordlimit = $this->get_word_limit()) {
            $constraints[] = get_string('summaryconstraint:wordlimit', 'aiprovider_groq', $wordlimit);
        }
        if ($this->is_single_paragraph()) {
            $constraints[] = get_string('summaryconstraint:singleparagraph', 'aiprovider_groq');
        }

        if ($constraints) {
            $instruction = trim($instruction . "\n\n" . implode(' ', $constraints));
        }

        return $instruction;
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * Models regularly ignore length and formatting instructions, so the configured
     * constraints are also applied to the returned text.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        $result = parent::handle_api_success($response);
        if (empty($result['success']) || empty($result['generatedcontent'])) {
            return $result;
        }

        $content = trim((string) $result['generatedcontent']);

        if ($this->is_single_paragraph()) {
            $content = trim(preg_replace(['/\R+/u', '/\s{2,}/u'], ' ', $content));
        }

        $result['generatedcontent'] = $this->apply_word_limit($content, $this->get_word_limit());

        return $result;
    }

    /**
     * Truncate text to a maximum number of words.
     *
     * The cut is made at the last sentence boundary within the limit where one exists,
     * so the summary does not end mid-sentence.
     *
     * @param string $content The text to truncate.
     * @param int $wordlimit The maximum number of words, or 0 for no limit.
     * @return string The truncated text.
     */
    protected function apply_word_limit(string $content, int $wordlimit): string {
        if ($wordlimit <= 0) {
            return $content;
        }

        $words = preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || count($words) <= $wordlimit) {
            return $content;
        }

        $truncated = implode(' ', array_slice($words, 0, $wordlimit));

        // Already ends on a complete sentence.
        if (preg_match('/[.!?]["\')\\]]*$/u', $truncated)) {
            return $truncated;
        }

        // Otherwise cut back to the last sentence boundary, but only if that keeps most of the text.
        if (preg_match('/^(.*[.!?]["\')\\]]*)\\s/us', $truncated, $matches)) {
            $sentence = rtrim($matches[1]);
            if (\core_text::strlen($sentence) >= (int) (\core_text::strlen($truncated) * 0.6)) {
                return $sentence;
            }
        }

        return rtrim($truncated, " \t\n\r\0\x0B,;:-") . '...';
    }
}
