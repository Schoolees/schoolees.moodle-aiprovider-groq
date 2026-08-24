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

use aiprovider_groq\test\testcase_helper_trait;
use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;

/**
 * Test the summarise text processor.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_groq\process_summarise_text
 */
final class process_summarise_text_test extends \advanced_testcase {
    use testcase_helper_trait;

    /** @var string A successful response in JSON format. */
    protected string $responsebodyjson;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->responsebodyjson = file_get_contents(self::get_fixture_path('aiprovider_groq', 'text_request_success.json'));
        $this->provider = $this->create_provider(\core_ai\aiactions\summarise_text::class);
        $this->action = new \core_ai\aiactions\summarise_text(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
        );
    }

    /**
     * Build a processor with the given summarise settings.
     *
     * @param array $settings Action settings to apply.
     * @return process_summarise_text
     */
    private function get_processor(array $settings = []): process_summarise_text {
        $provider = $settings
            ? $this->create_provider(\core_ai\aiactions\summarise_text::class, $settings)
            : $this->provider;

        return new process_summarise_text($provider, $this->action);
    }

    /**
     * Build a 200 response carrying the given generated content.
     *
     * @param string $content The content the model returned.
     * @return Response
     */
    private function content_response(string $content): Response {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'chatcmpl-test',
            'model' => abstract_processor::DEFAULT_MODEL,
            'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 568],
        ]));
    }

    /**
     * Test create_request_object.
     */
    public function test_create_request_object(): void {
        $processor = $this->get_processor();

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, 'hasheduserid')->getBody()->getContents());

        $this->assertEquals('system', $body->messages[0]->role);
        $this->assertStringContainsString(
            get_string('action_summarise_text_instruction', 'core_ai'),
            $body->messages[0]->content,
        );
        // The configured guardrails are also asked for in the prompt.
        $this->assertStringContainsString('maximum of 500 words', $body->messages[0]->content);
        $this->assertStringContainsString('single paragraph', $body->messages[0]->content);
        $this->assertEquals('This is a test prompt', $body->messages[1]->content);
        $this->assertEquals('user', $body->messages[1]->role);
    }

    /**
     * With the guardrails switched off, nothing is appended to the instruction.
     */
    public function test_create_request_object_without_guardrails(): void {
        $processor = $this->get_processor(['wordlimit' => 0, 'singleparagraph' => 0]);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, 'hasheduserid')->getBody()->getContents());

        $this->assertEquals(
            get_string('action_summarise_text_instruction', 'core_ai'),
            $body->messages[0]->content,
        );
    }

    /**
     * Test the API success response handler method.
     */
    public function test_handle_api_success(): void {
        $processor = $this->get_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, new Response(
            200,
            ['Content-Type' => 'application/json'],
            $this->responsebodyjson,
        ));

        $this->assertTrue($result['success']);
        $this->assertEquals('chatcmpl-9lkwPWOIiQEvI3nfcGofJcmS5lPYo', $result['id']);
        $this->assertStringContainsString('Sure, here is some sample text', $result['generatedcontent']);
        $this->assertEquals('stop', $result['finishreason']);
    }

    /**
     * A multi-line summary is collapsed when the single paragraph guardrail is on.
     */
    public function test_handle_api_success_collapses_paragraphs(): void {
        $processor = $this->get_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->content_response("First line.\n\n- Second line.\n- Third line."));

        $this->assertEquals('First line. - Second line. - Third line.', $result['generatedcontent']);
    }

    /**
     * Line breaks survive when the single paragraph guardrail is off.
     */
    public function test_handle_api_success_keeps_paragraphs_when_disabled(): void {
        $processor = $this->get_processor(['singleparagraph' => 0]);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->content_response("First line.\n\nSecond line."));

        $this->assertEquals("First line.\n\nSecond line.", $result['generatedcontent']);
    }

    /**
     * An over-long summary is cut back at a sentence boundary.
     */
    public function test_handle_api_success_applies_word_limit(): void {
        $processor = $this->get_processor(['wordlimit' => 6]);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->content_response(
            'Alpha beta gamma delta epsilon. Zeta eta theta iota kappa.',
        ));

        $this->assertEquals('Alpha beta gamma delta epsilon.', $result['generatedcontent']);
    }

    /**
     * With no sentence boundary to cut at, the text is trimmed and marked as truncated.
     */
    public function test_handle_api_success_word_limit_without_sentence_break(): void {
        $processor = $this->get_processor(['wordlimit' => 3]);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $this->content_response('one two three four five six seven'));

        $this->assertEquals('one two three...', $result['generatedcontent']);
    }

    /**
     * A word limit of zero leaves the summary untouched.
     */
    public function test_handle_api_success_without_word_limit(): void {
        $processor = $this->get_processor(['wordlimit' => 0]);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $content = 'one two three four five six seven eight nine ten eleven twelve';
        $result = $method->invoke($processor, $this->content_response($content));

        $this->assertEquals($content, $result['generatedcontent']);
    }

    /**
     * The guardrails must not turn a failed response into a success.
     */
    public function test_handle_api_success_passes_through_failure(): void {
        $processor = $this->get_processor();
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['choices' => []]),
        ));

        $this->assertFalse($result['success']);
    }

    /**
     * Test process method.
     */
    public function test_process(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));

        $result = $this->get_processor()->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('summarise_text', $result->get_actionname());
    }

    /**
     * Test process method with error.
     */
    public function test_process_error(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid Authentication']]),
        ));

        $result = $this->get_processor()->process();

        $this->assertFalse($result->get_success());
        $this->assertEquals('summarise_text', $result->get_actionname());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Invalid Authentication', $result->get_errormessage());
    }
}
