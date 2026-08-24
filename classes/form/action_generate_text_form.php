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

use aiprovider_groq\abstract_processor;
use aiprovider_groq\process_summarise_text;
use core_ai\form\action_settings_form;

/**
 * Action settings form for the text actions (generate, summarise and explain).
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_generate_text_form extends action_settings_form {
    /** @var array The currently stored settings for this action. */
    protected array $actionconfig;

    /** @var string|null The URL to return to after saving. */
    protected ?string $returnurl;

    /** @var string The short action name, for example generate_text. */
    protected string $actionname;

    /** @var string The fully qualified action class name. */
    protected string $action;

    /** @var int The provider instance id. */
    protected int $providerid;

    /** @var string The provider plugin name. */
    protected string $providername;

    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $this->actionconfig = $this->_customdata['actionconfig']['settings'] ?? [];
        $this->returnurl = isset($this->_customdata['returnurl']) ? (string) $this->_customdata['returnurl'] : null;
        $this->actionname = $this->_customdata['actionname'] ?? 'generate_text';
        $this->action = $this->_customdata['action'] ?? \core_ai\aiactions\generate_text::class;
        $this->providerid = (int) ($this->_customdata['providerid'] ?? 0);
        $this->providername = $this->_customdata['providername'] ?? 'aiprovider_groq';

        $mform->addElement('header', 'generalsettingsheader', get_string('general', 'core'));

        // Model.
        $mform->addElement(
            'text',
            'model',
            get_string("action:{$this->actionname}:model", 'aiprovider_groq'),
            'maxlength="255" size="40"',
        );
        $mform->setType('model', PARAM_TEXT);
        $mform->addRule('model', get_string('required'), 'required', null, 'client');
        $mform->setDefault('model', $this->actionconfig['model'] ?? abstract_processor::DEFAULT_MODEL);
        $mform->addHelpButton('model', "action:{$this->actionname}:model", 'aiprovider_groq');

        // API endpoint.
        $mform->addElement(
            'text',
            'endpoint',
            get_string("action:{$this->actionname}:endpoint", 'aiprovider_groq'),
            'maxlength="255" size="40"',
        );
        $mform->setType('endpoint', PARAM_URL);
        $mform->addRule('endpoint', get_string('required'), 'required', null, 'client');
        $mform->setDefault('endpoint', $this->actionconfig['endpoint'] ?? abstract_processor::DEFAULT_ENDPOINT);
        $mform->addHelpButton('endpoint', "action:{$this->actionname}:endpoint", 'aiprovider_groq');

        // Temperature.
        $mform->addElement(
            'text',
            'temperature',
            get_string("action:{$this->actionname}:temperature", 'aiprovider_groq'),
            'maxlength="10" size="6"',
        );
        $mform->setType('temperature', PARAM_FLOAT);
        $mform->setDefault('temperature', $this->actionconfig['temperature'] ?? abstract_processor::DEFAULT_TEMPERATURE);
        $mform->addHelpButton('temperature', "action:{$this->actionname}:temperature", 'aiprovider_groq');

        // System instruction.
        $mform->addElement(
            'textarea',
            'systeminstruction',
            get_string("action:{$this->actionname}:systeminstruction", 'aiprovider_groq'),
            'wrap="virtual" rows="8" cols="60"',
        );
        $mform->setType('systeminstruction', PARAM_TEXT);
        $mform->setDefault(
            'systeminstruction',
            $this->actionconfig['systeminstruction'] ?? $this->action::get_system_instruction(),
        );
        $mform->addHelpButton('systeminstruction', "action:{$this->actionname}:systeminstruction", 'aiprovider_groq');

        if ($this->actionname === 'summarise_text') {
            $this->add_summary_fields();
        }

        if ($this->returnurl) {
            $mform->addElement('hidden', 'returnurl', $this->returnurl);
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        // The core /ai/configure_actions.php handler requires all three of these to be posted back.
        $mform->addElement('hidden', 'action', $this->action);
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('hidden', 'provider', $this->providername);
        $mform->setType('provider', PARAM_PLUGIN);

        $mform->addElement('hidden', 'providerid', $this->providerid);
        $mform->setType('providerid', PARAM_INT);

        $this->set_data($this->actionconfig);
    }

    /**
     * Add the output guardrails that only apply to summarised text.
     */
    protected function add_summary_fields(): void {
        $mform = $this->_form;

        $mform->addElement(
            'text',
            'wordlimit',
            get_string('action:summarise_text:wordlimit', 'aiprovider_groq'),
            'maxlength="6" size="6"',
        );
        $mform->setType('wordlimit', PARAM_INT);
        $mform->setDefault('wordlimit', $this->actionconfig['wordlimit'] ?? process_summarise_text::DEFAULT_WORD_LIMIT);
        $mform->addHelpButton('wordlimit', 'action:summarise_text:wordlimit', 'aiprovider_groq');

        $mform->addElement(
            'advcheckbox',
            'singleparagraph',
            get_string('action:summarise_text:singleparagraph', 'aiprovider_groq'),
        );
        $mform->setType('singleparagraph', PARAM_INT);
        $mform->setDefault(
            'singleparagraph',
            $this->actionconfig['singleparagraph'] ?? process_summarise_text::DEFAULT_SINGLE_PARAGRAPH,
        );
        $mform->addHelpButton('singleparagraph', 'action:summarise_text:singleparagraph', 'aiprovider_groq');
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Groq accepts a sampling temperature between 0 and 2 inclusive.
        if (isset($data['temperature']) && ($data['temperature'] < 0 || $data['temperature'] > 2)) {
            $errors['temperature'] = get_string('error:temperaturerange', 'aiprovider_groq');
        }

        if (isset($data['wordlimit']) && $data['wordlimit'] < 0) {
            $errors['wordlimit'] = get_string('error:wordlimitrange', 'aiprovider_groq');
        }

        return $errors;
    }

    #[\Override]
    public function get_defaults(): array {
        $data = parent::get_defaults();
        unset($data['returnurl']);

        return $data;
    }
}
