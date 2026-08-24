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

/**
 * Strings for component aiprovider_groq, language 'en'.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action:explain_text:endpoint'] = 'API endpoint';
$string['action:explain_text:endpoint_help'] = 'The Groq chat completions endpoint used to explain text. Change this only if you are routing requests through a compatible proxy.';
$string['action:explain_text:model'] = 'AI model';
$string['action:explain_text:model_help'] = 'The model used to explain the selected text. The models available to your account are listed at https://console.groq.com/docs/models.';
$string['action:explain_text:systeminstruction'] = 'System instruction';
$string['action:explain_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing it is not recommended unless absolutely required.';
$string['action:explain_text:temperature'] = 'Temperature';
$string['action:explain_text:temperature_help'] = 'The randomness or creativity of the response. A low temperature produces more coherent but more predictable text. The range is 0 to 2.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:endpoint_help'] = 'The Groq chat completions endpoint used to generate text. Change this only if you are routing requests through a compatible proxy.';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_help'] = 'The model used to generate the text response. The models available to your account are listed at https://console.groq.com/docs/models.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing it is not recommended unless absolutely required.';
$string['action:generate_text:temperature'] = 'Temperature';
$string['action:generate_text:temperature_help'] = 'The randomness or creativity of the response. A low temperature produces more coherent but more predictable text. The range is 0 to 2.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:endpoint_help'] = 'The Groq chat completions endpoint used to summarise text. Change this only if you are routing requests through a compatible proxy.';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_help'] = 'The model used to summarise the provided text. The models available to your account are listed at https://console.groq.com/docs/models.';
$string['action:summarise_text:singleparagraph'] = 'Force a single paragraph';
$string['action:summarise_text:singleparagraph_help'] = 'Collapse the returned summary into a single paragraph by removing line breaks and bullet points. Models often ignore this instruction, so it is also applied to the response.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing it is not recommended unless absolutely required.';
$string['action:summarise_text:temperature'] = 'Temperature';
$string['action:summarise_text:temperature_help'] = 'The randomness or creativity of the response. A low temperature produces more coherent but more predictable text. The range is 0 to 2.';
$string['action:summarise_text:wordlimit'] = 'Maximum words in a summary';
$string['action:summarise_text:wordlimit_help'] = 'Summaries longer than this are cut back, at a sentence boundary where possible. Set to 0 to return the summary unchanged.';
$string['apikey'] = 'Groq API key';
$string['apikey_help'] = 'The API key used to authenticate with Groq Cloud. Create one in the API keys section of the Groq console at https://console.groq.com/keys.';
$string['error:temperaturerange'] = 'The temperature must be between 0 and 2.';
$string['error:wordlimitrange'] = 'The word limit cannot be negative. Use 0 for no limit.';
$string['pluginname'] = 'Schoolees Groq AI provider';
$string['privacy:metadata:aiprovider_groq:externalpurpose'] = 'This information is sent to the Groq API so that a response can be generated. Your Groq account settings may change how Groq stores and retains this data. No user data is explicitly sent to Groq or stored in Moodle by this plugin.';
$string['privacy:metadata:aiprovider_groq:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_groq:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_groq:systeminstruction'] = 'The system instruction configured for the action, sent alongside the prompt.';
$string['privacy:metadata:aiprovider_groq:temperature'] = 'The sampling temperature used to generate the response.';
$string['privacy:metadata:aiprovider_groq:userid'] = 'A one way hash of the site identifier and the Moodle user id. It allows requests to be attributed without disclosing the user.';
$string['summaryconstraint:singleparagraph'] = 'Write it as a single paragraph, with no bullet points and no line breaks.';
$string['summaryconstraint:wordlimit'] = 'Limit the summary to a maximum of {$a} words.';
$string['testaiconfiguration'] = 'Test AI configuration';
$string['testaiservices'] = 'Make a test request';
$string['testfailure'] = 'The test request failed with error {$a->code}: {$a->message}';
$string['testintro'] = 'This sends a short generate text request through the Moodle AI subsystem to confirm that an enabled Groq provider instance is reachable and correctly configured. The request counts against your Groq quota.';
$string['testprompt'] = 'Reply with the single word: connected';
$string['testsuccess'] = 'The test request succeeded. The response from Groq is shown below.';
