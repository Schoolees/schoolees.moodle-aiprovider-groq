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
 * Admin-only diagnostic page for verifying the Groq provider configuration.
 *
 * @package    aiprovider_groq
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/ai/provider/groq/test_connection.php');
$title = get_string('testaiconfiguration', 'aiprovider_groq');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading($SITE->fullname);

// The test spends real API quota, so it only runs on an explicit, session-key protected POST.
$run = optional_param('run', 0, PARAM_BOOL);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('testintro', 'aiprovider_groq'));

if ($run && confirm_sesskey()) {
    $action = new \core_ai\aiactions\generate_text(
        contextid: $context->id,
        userid: $USER->id,
        prompttext: get_string('testprompt', 'aiprovider_groq'),
    );

    $result = \core\di::get(\core_ai\manager::class)->process_action($action);

    if ($result->get_success()) {
        echo $OUTPUT->notification(
            get_string('testsuccess', 'aiprovider_groq'),
            \core\output\notification::NOTIFY_SUCCESS,
        );
        echo html_writer::tag('pre', s($result->get_response_data()['generatedcontent'] ?? ''));
    } else {
        echo $OUTPUT->notification(
            get_string('testfailure', 'aiprovider_groq', (object) [
                'code' => $result->get_errorcode(),
                'message' => s($result->get_errormessage()),
            ]),
            \core\output\notification::NOTIFY_ERROR,
        );
    }
}

echo $OUTPUT->single_button(
    new moodle_url($url, ['run' => 1]),
    get_string('testaiservices', 'aiprovider_groq'),
    'post',
);
echo $OUTPUT->footer();
