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
 * Test the generate text processor.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_groq\process_generate_text
 * @covers     \aiprovider_groq\abstract_processor
 */
final class process_generate_text_test extends \advanced_testcase {
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
        $this->provider = $this->create_provider(\core_ai\aiactions\generate_text::class, [
            'systeminstruction' => 'You are a helpful assistant.',
        ]);
        $this->action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
        );
    }

    /**
     * Test create_request_object.
     */
    public function test_create_request_object(): void {
        $processor = new process_generate_text($this->provider, $this->action);

        // We're working with a protected method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 'hasheduserid');

        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals(abstract_processor::DEFAULT_MODEL, $body->model);
        $this->assertEquals('hasheduserid', $body->user);
        $this->assertEquals('system', $body->messages[0]->role);
        $this->assertEquals('You are a helpful assistant.', $body->messages[0]->content);
        $this->assertEquals('user', $body->messages[1]->role);
        $this->assertEquals('This is a test prompt', $body->messages[1]->content);
    }

    /**
     * With no system instruction configured, the action default is used.
     */
    public function test_create_request_object_uses_action_default_instruction(): void {
        $provider = $this->create_provider(\core_ai\aiactions\generate_text::class);
        $processor = new process_generate_text($provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, 'hasheduserid')->getBody()->getContents());

        $this->assertEquals(
            get_string('action_generate_text_instruction', 'core_ai'),
            $body->messages[0]->content,
        );
    }

    /**
     * Test the API error response handler method.
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => [new Response(500, ['Content-Type' => 'application/json']), 'Internal Server Error'],
            503 => [new Response(503, ['Content-Type' => 'application/json']), 'Service Unavailable'],
            401 => [
                new Response(
                    401,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => ['message' => 'Invalid Authentication']])
                ),
                'Invalid Authentication',
            ],
            404 => [
                new Response(
                    404,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => ['message' => 'The model does not exist']])
                ),
                'The model does not exist',
            ],
            429 => [
                new Response(
                    429,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => ['message' => 'Rate limit reached for requests']])
                ),
                'Rate limit reached for requests',
            ],
        ];

        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => [$response, $expectedmessage]) {
            $result = $method->invoke($processor, $response);
            $this->assertFalse($result['success']);
            $this->assertEquals($status, $result['errorcode']);
            $this->assertEquals($expectedmessage, $result['errormessage']);
        }
    }

    /**
     * A non JSON error body must not be echoed back in full.
     */
    public function test_handle_api_error_truncates_non_json_body(): void {
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        // 599 has no standard reason phrase, so the body is the only thing left to report.
        $response = new Response(599, ['Content-Type' => 'text/html'], str_repeat('a', 5000));
        $result = $method->invoke($processor, $response);

        $this->assertEquals(599, $result['errorcode']);
        $this->assertEquals(500, strlen($result['errormessage']));
    }

    /**
     * Test the API success response handler method.
     */
    public function test_handle_api_success(): void {
        $response = new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson);

        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('chatcmpl-9lkwPWOIiQEvI3nfcGofJcmS5lPYo', $result['id']);
        $this->assertEquals('fp_c4e5b6fa31', $result['fingerprint']);
        $this->assertStringContainsString('Sure, here is some sample text', $result['generatedcontent']);
        $this->assertEquals('stop', $result['finishreason']);
        $this->assertEquals('11', $result['prompttokens']);
        $this->assertEquals('568', $result['completiontokens']);
    }

    /**
     * Groq omits some OpenAI fields, and a partial response must not raise an error.
     */
    public function test_handle_api_success_with_minimal_body(): void {
        $body = json_encode([
            'choices' => [['message' => ['content' => 'Hello there']]],
        ]);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);

        $processor = new process_generate_text($this->provider, $this->action);
        $result = (new \ReflectionMethod($processor, 'handle_api_success'))->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('Hello there', $result['generatedcontent']);
        $this->assertNull($result['id']);
        $this->assertNull($result['fingerprint']);
        $this->assertNull($result['prompttokens']);
        $this->assertEquals(abstract_processor::DEFAULT_MODEL, $result['model']);
    }

    /**
     * A 200 response with no usable content is reported as a failure.
     */
    public function test_handle_api_success_with_empty_content(): void {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode(['choices' => []]));

        $processor = new process_generate_text($this->provider, $this->action);
        $result = (new \ReflectionMethod($processor, 'handle_api_success'))->invoke($processor, $response);

        $this->assertFalse($result['success']);
        $this->assertEquals(-1, $result['errorcode']);
    }

    /**
     * Test query_ai_api for a successful call.
     */
    public function test_query_ai_api_success(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));

        $processor = new process_generate_text($this->provider, $this->action);
        $result = (new \ReflectionMethod($processor, 'query_ai_api'))->invoke($processor);

        $this->assertTrue($result['success']);
        $this->assertEquals('chatcmpl-9lkwPWOIiQEvI3nfcGofJcmS5lPYo', $result['id']);
        $this->assertStringContainsString('Sure, here is some sample text', $result['generatedcontent']);
    }

    /**
     * A malformed body from the service must be reported, not thrown.
     */
    public function test_query_ai_api_with_malformed_body(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], 'not json at all'));

        $processor = new process_generate_text($this->provider, $this->action);
        $result = (new \ReflectionMethod($processor, 'query_ai_api'))->invoke($processor);

        $this->assertFalse($result['success']);
        $this->assertEquals(-1, $result['errorcode']);
    }

    /**
     * Test process method.
     */
    public function test_process(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));

        $processor = new process_generate_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertStringContainsString('Sure, here is some sample text', $result->get_response_data()['generatedcontent']);
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

        $processor = new process_generate_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertFalse($result->get_success());
        $this->assertEquals('generate_text', $result->get_actionname());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Invalid Authentication', $result->get_errormessage());
    }

    /**
     * Test process method with the user rate limiter enabled.
     */
    public function test_process_with_user_rate_limiter(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $provider = $this->create_provider(\core_ai\aiactions\generate_text::class, config: [
            'enableuserratelimit' => 1,
            'userratelimit' => 1,
        ]);

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));

        $this->assertTrue((new process_generate_text($provider, $this->action))->process()->get_success());

        $result = (new process_generate_text($provider, $this->action))->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
    }
}
