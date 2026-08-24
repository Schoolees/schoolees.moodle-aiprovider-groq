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

use core_ai\hook\after_ai_provider_form_hook;

/**
 * Hook listeners for the Groq AI provider plugin.
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Add the provider instance settings to the AI provider form.
     *
     * Core populates the field values from the stored instance config after this
     * hook is dispatched, so no defaults are set here.
     *
     * @param after_ai_provider_form_hook $hook The hook to add to the AI instance setup.
     */
    public static function set_form_definition_for_aiprovider_groq(after_ai_provider_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_groq') {
            return;
        }

        $mform = $hook->mform;

        // Required setting to store the Groq API key.
        $mform->addElement(
            'passwordunmask',
            'apikey',
            get_string('apikey', 'aiprovider_groq'),
            ['size' => 75],
        );
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('apikey', 'apikey', 'aiprovider_groq');
        $mform->addRule('apikey', get_string('required'), 'required', null, 'client');
    }
}
