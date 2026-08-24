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
 * Test the explain text processor.
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_groq\process_explain_text
 */
final class process_explain_text_test extends \advanced_testcase {
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
        $this->provider = $this->create_provider(\core_ai\aiactions\explain_text::class, [
            'model' => 'llama-3.3-70b-versatile',
        ]);
        $this->action = new \core_ai\aiactions\explain_text(
            contextid: 1,
            userid: 1,
            prompttext: 'Explain this passage',
        );
    }

    /**
     * The explain action reads its own settings, not those of generate_text.
     */
    public function test_create_request_object(): void {
        $processor = new process_explain_text($this->provider, $this->action);

        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, 'hasheduserid')->getBody()->getContents());

        $this->assertEquals('llama-3.3-70b-versatile', $body->model);
        $this->assertEquals('system', $body->messages[0]->role);
        $this->assertEquals(
            get_string('action_explain_text_instruction', 'core_ai'),
            $body->messages[0]->content,
        );
        $this->assertEquals('Explain this passage', $body->messages[1]->content);
    }

    /**
     * Test process method.
     */
    public function test_process(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], $this->responsebodyjson));

        $result = (new process_explain_text($this->provider, $this->action))->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('explain_text', $result->get_actionname());
    }
}
