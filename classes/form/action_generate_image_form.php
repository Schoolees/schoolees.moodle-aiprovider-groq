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
 * Action settings form for generate_image.
 *
 * @package    aiprovider_groq
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_generate_image_form extends action_settings_form {
    #[\Override]
    protected function definition(): void {
        \aiprovider_groq\compat::ensure_moodle_pear_loaded();

        $mform = $this->_form;
        $actionname = $this->_customdata['actionname'] ?? 'generate_image';

        $rawactionconfig = $this->_customdata['actionconfig'] ?? [];
        $settings = $rawactionconfig['settings'] ?? $rawactionconfig;

        $mform->addElement(
            'text',
            'model',
            get_string("action:{$actionname}:model", 'aiprovider_groq')
        );
        $mform->setType('model', PARAM_TEXT);
        $mform->addRule('model', null, 'required', null, 'client');
        $mform->setDefault('model', $settings['model'] ?? 'dall-e-3');

        $mform->addElement(
            'text',
            'endpoint',
            get_string("action:{$actionname}:endpoint", 'aiprovider_groq')
        );
        $mform->setType('endpoint', PARAM_URL);
        $mform->addRule('endpoint', null, 'required', null, 'client');
        $mform->setDefault('endpoint', $settings['endpoint'] ?? 'https://api.openai.com/v1/images/generations');
    }
}
