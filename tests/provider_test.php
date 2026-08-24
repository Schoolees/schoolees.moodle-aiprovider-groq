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

/**
 * Test the Groq provider methods.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_groq\provider
 */
final class provider_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test get_action_list.
     */
    public function test_get_action_list(): void {
        $actionlist = provider::get_action_list();

        $this->assertIsArray($actionlist);
        $this->assertCount(3, $actionlist);
        $this->assertContains(\core_ai\aiactions\generate_text::class, $actionlist);
        $this->assertContains(\core_ai\aiactions\summarise_text::class, $actionlist);
        $this->assertContains(\core_ai\aiactions\explain_text::class, $actionlist);
    }

    /**
     * Groq has no image generation API, so the action must not be advertised.
     */
    public function test_generate_image_is_not_supported(): void {
        $this->assertNotContains(\core_ai\aiactions\generate_image::class, provider::get_action_list());
        $this->assertFalse(provider::get_action_settings(\core_ai\aiactions\generate_image::class));
        $this->assertSame([], provider::get_action_setting_defaults(\core_ai\aiactions\generate_image::class));
    }

    /**
     * Test generate_userid.
     */
    public function test_generate_userid(): void {
        $this->resetAfterTest();
        $provider = $this->create_provider(\core_ai\aiactions\generate_text::class);

        $userid = $provider->generate_userid('1');

        // Assert that the generated userid is a hash and does not leak the user id.
        $this->assertIsString($userid);
        $this->assertEquals(64, strlen($userid));
        $this->assertNotEquals($userid, $provider->generate_userid('2'));
    }

    /**
     * Test add_authentication_headers.
     */
    public function test_add_authentication_headers(): void {
        $this->resetAfterTest();
        $provider = $this->create_provider(
            \core_ai\aiactions\generate_text::class,
            config: ['apikey' => 'sekret'],
        );

        $request = $provider->add_authentication_headers(new \GuzzleHttp\Psr7\Request('POST', ''));

        // A single header, so that re-authenticating a request cannot stack credentials.
        $this->assertSame(['Bearer sekret'], $request->getHeader('Authorization'));
        $this->assertSame(['Bearer sekret'], $provider->add_authentication_headers($request)->getHeader('Authorization'));
    }

    /**
     * Test is_request_allowed, which is inherited from core and reads the instance config.
     */
    public function test_is_request_allowed(): void {
        $this->resetAfterTest();

        $provider = $this->create_provider(\core_ai\aiactions\generate_text::class, config: [
            'enableuserratelimit' => 1,
            'userratelimit' => 3,
            'enableglobalratelimit' => 1,
            'globalratelimit' => 5,
        ]);

        $makeaction = fn(int $userid): \core_ai\aiactions\generate_text => new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: $userid,
            prompttext: 'This is a test prompt',
        );

        // Three requests for user 1 are allowed.
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($provider->is_request_allowed($makeaction(1)));
        }

        // The fourth request for the same user is denied.
        $result = $provider->is_request_allowed($makeaction(1));
        $this->assertFalse($result['success']);
        $this->assertEquals(429, $result['errorcode']);
        $this->assertEquals('User rate limit exceeded', $result['errormessage']);

        // A different user is still allowed, up to the site wide limit of five.
        $this->assertTrue($provider->is_request_allowed($makeaction(2)));
        $this->assertTrue($provider->is_request_allowed($makeaction(2)));

        $result = $provider->is_request_allowed($makeaction(2));
        $this->assertFalse($result['success']);
        $this->assertEquals('Global rate limit exceeded', $result['errormessage']);
    }

    /**
     * Test is_provider_configured.
     */
    public function test_is_provider_configured(): void {
        $this->resetAfterTest();

        $unconfigured = $this->create_provider(\core_ai\aiactions\generate_text::class, config: ['apikey' => '']);
        $this->assertFalse($unconfigured->is_provider_configured());

        $configured = $this->create_provider(\core_ai\aiactions\generate_text::class, config: ['apikey' => '123']);
        $this->assertTrue($configured->is_provider_configured());
    }

    /**
     * Test get_action_settings returns the shared text form for every supported action.
     */
    public function test_get_action_settings(): void {
        $this->resetAfterTest();

        foreach (provider::get_action_list() as $action) {
            $form = provider::get_action_settings($action, [
                'providername' => 'aiprovider_groq',
                'providerid' => 0,
            ]);
            $this->assertInstanceOf(\aiprovider_groq\form\action_generate_text_form::class, $form);
        }
    }

    /**
     * A new provider instance must come with a usable model and endpoint already set.
     */
    public function test_get_action_setting_defaults(): void {
        $this->resetAfterTest();

        foreach (provider::get_action_list() as $action) {
            $defaults = provider::get_action_setting_defaults($action);
            $this->assertEquals(abstract_processor::DEFAULT_MODEL, $defaults['model']);
            $this->assertEquals(abstract_processor::DEFAULT_ENDPOINT, $defaults['endpoint']);
            $this->assertEquals(abstract_processor::DEFAULT_TEMPERATURE, $defaults['temperature']);
            $this->assertNotEmpty($defaults['systeminstruction']);
            $this->assertArrayNotHasKey('returnurl', $defaults);
        }

        // The summarise action carries the extra output guardrails.
        $defaults = provider::get_action_setting_defaults(\core_ai\aiactions\summarise_text::class);
        $this->assertEquals(process_summarise_text::DEFAULT_WORD_LIMIT, $defaults['wordlimit']);
        $this->assertEquals(process_summarise_text::DEFAULT_SINGLE_PARAGRAPH, $defaults['singleparagraph']);
    }

    /**
     * The action config is keyed by the action class name, so the processors must read it that way.
     */
    public function test_processor_reads_configured_action_settings(): void {
        $this->resetAfterTest();

        $provider = $this->create_provider(\core_ai\aiactions\generate_text::class, [
            'model' => 'llama-3.3-70b-versatile',
            'endpoint' => 'https://proxy.example.com/v1/chat/completions',
            'temperature' => '1.5',
            'systeminstruction' => 'Be brief.',
        ]);
        $action = new \core_ai\aiactions\generate_text(
            contextid: 1,
            userid: 1,
            prompttext: 'This is a test prompt',
        );

        $processor = new process_generate_text($provider, $action);
        $request = (new \ReflectionMethod($processor, 'create_request_object'))->invoke($processor, 'abc');
        $body = json_decode($request->getBody()->getContents());

        $this->assertEquals('llama-3.3-70b-versatile', $body->model);
        $this->assertEquals(1.5, $body->temperature);
        $this->assertEquals('Be brief.', $body->messages[0]->content);
        $this->assertEquals(
            'https://proxy.example.com/v1/chat/completions',
            (string) (new \ReflectionMethod($processor, 'get_endpoint'))->invoke($processor),
        );
    }
}
