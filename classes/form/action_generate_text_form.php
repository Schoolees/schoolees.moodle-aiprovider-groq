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

namespace aiprovider_groq\form;

use core_ai\form\action_settings_form;

/**
 * Action settings form for text actions (generate_text / summarise_text).
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_generate_text_form extends action_settings_form {
    #[\Override]
    protected function definition(): void {
        \aiprovider_groq\compat::ensure_moodle_pear_loaded();

        $mform = $this->_form;

        // Required context for core /ai/configure_actions.php processing.
        $providername = $this->_customdata['providername'] ?? '';
        $providerid = $this->_customdata['providerid'] ?? 0;
        $action = $this->_customdata['action'] ?? '';
        $returnurl = $this->_customdata['returnurl'] ?? '';

        $mform->addElement('hidden', 'provider', $providername);
        $mform->setType('provider', PARAM_PLUGIN);

        $mform->addElement('hidden', 'providerid', $providerid);
        $mform->setType('providerid', PARAM_INT);

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);

        if (!empty($returnurl)) {
            $mform->addElement('hidden', 'returnurl', (string) $returnurl);
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        $actionname = $this->_customdata['actionname'] ?? 'generate_text';
        $action = $this->_customdata['action'] ?? null;

        $rawactionconfig = $this->_customdata['actionconfig'] ?? [];
        $settings = $rawactionconfig['settings'] ?? $rawactionconfig;

        $mform->addElement(
            'text',
            'model',
            get_string("action:{$actionname}:model", 'aiprovider_groq')
        );
        $mform->setType('model', PARAM_TEXT);
        $mform->addRule('model', null, 'required', null, 'client');
        $mform->setDefault('model', $settings['model'] ?? 'llama-3.1-8b-instant');

        $mform->addElement(
            'text',
            'endpoint',
            get_string("action:{$actionname}:endpoint", 'aiprovider_groq')
        );
        $mform->setType('endpoint', PARAM_URL);
        $mform->addRule('endpoint', null, 'required', null, 'client');
        $mform->setDefault('endpoint', $settings['endpoint'] ?? 'https://api.groq.com/openai/v1/chat/completions');

        $mform->addElement(
            'text',
            'temperature',
            get_string("action:{$actionname}:temperature", 'aiprovider_groq')
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->setDefault('temperature', $settings['temperature'] ?? '0.2');

        $mform->addElement(
            'textarea',
            'systeminstruction',
            get_string("action:{$actionname}:systeminstruction", 'aiprovider_groq'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('systeminstruction', PARAM_TEXT);
        $mform->setDefault('systeminstruction', $settings['systeminstruction'] ?? ($action ? $action::get_system_instruction() : ''));
    }
}
