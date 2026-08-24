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

namespace aiprovider_groq\test;

use aiprovider_groq\abstract_processor;

/**
 * Shared helpers for the Groq provider test cases.
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {
    /**
     * Create a Groq provider instance for testing.
     *
     * @param string $actionclass The fully qualified action class the settings belong to.
     * @param array $actionsettings Extra or overriding action settings.
     * @param array $config Extra or overriding instance config.
     * @return \core_ai\provider The persisted provider instance.
     */
    protected function create_provider(
        string $actionclass,
        array $actionsettings = [],
        array $config = [],
    ): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);

        $actionconfig = [
            $actionclass => [
                'enabled' => true,
                'settings' => array_merge([
                    'model' => abstract_processor::DEFAULT_MODEL,
                    'endpoint' => abstract_processor::DEFAULT_ENDPOINT,
                    'temperature' => abstract_processor::DEFAULT_TEMPERATURE,
                ], $actionsettings),
            ],
        ];

        return $manager->create_provider_instance(
            classname: \aiprovider_groq\provider::class,
            name: 'Groq test instance',
            enabled: true,
            config: array_merge(['apikey' => '123'], $config),
            actionconfig: $actionconfig,
        );
    }
}
