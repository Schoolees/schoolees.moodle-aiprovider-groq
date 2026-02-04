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
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Add provider-instance settings fields to the AI provider form (Moodle 5+).
     *
     * @param after_ai_provider_form_hook $hook
     */
    public static function set_form_definition_for_aiprovider_groq(after_ai_provider_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_groq') {
            return;
        }

        compat::ensure_moodle_pear_loaded();

        $mform = $hook->mform;
        $providerconfig = $hook->providerconfig ?? [];

        // Use the core QuickForm 'password' element for maximum compatibility.
        // (Some environments don’t have Moodle's password-unmask element available in this form context.)
        $mform->addElement('password', 'apikey', get_string('apikey', 'aiprovider_groq'));
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addRule('apikey', null, 'required', null, 'client');
        $mform->setDefault('apikey', $providerconfig['apikey'] ?? '');
        $mform->addElement('static', 'apikey_desc', '', get_string('apikey_desc', 'aiprovider_groq'));

        $mform->addElement('text', 'orgid', get_string('orgid', 'aiprovider_groq'));
        $mform->setType('orgid', PARAM_TEXT);
        $mform->setDefault('orgid', $providerconfig['orgid'] ?? '');
        $mform->addElement('static', 'orgid_desc', '', get_string('orgid_desc', 'aiprovider_groq'));
    }
}
