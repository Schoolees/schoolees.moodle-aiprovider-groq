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
 * Upgrade steps for aiprovider_groq.
 *
 * @package    aiprovider_groq
 * @copyright  2026 Schoolees
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_aiprovider_groq_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026082500) {
        // Moodle 5.0 replaced site-wide provider settings with provider instances, but core only
        // migrates its own bundled providers. Carry any settings left behind by the Moodle 4.5 era
        // version of this plugin across to a provider instance so upgrading sites keep working.
        $legacy = (array) get_config('aiprovider_groq');
        $hasinstance = $DB->record_exists('ai_providers', ['provider' => \aiprovider_groq\provider::class]);

        if (!$hasinstance && !empty($legacy['apikey'])) {
            $endpoint = $legacy['action_generate_text_endpoint']
                ?? \aiprovider_groq\abstract_processor::DEFAULT_ENDPOINT;
            $model = $legacy['action_generate_text_model']
                ?? \aiprovider_groq\abstract_processor::DEFAULT_MODEL;

            $instanceconfig = [
                'aiprovider' => \aiprovider_groq\provider::class,
                'name' => get_string('pluginname', 'aiprovider_groq'),
                'apikey' => $legacy['apikey'],
                'enableglobalratelimit' => $legacy['enableglobalratelimit'] ?? 0,
                'globalratelimit' => $legacy['globalratelimit'] ?? 100,
                'enableuserratelimit' => $legacy['enableuserratelimit'] ?? 0,
                'userratelimit' => $legacy['userratelimit'] ?? 10,
            ];

            $actionconfig = [];
            foreach (['generate_text', 'summarise_text', 'explain_text'] as $actionname) {
                $actionconfig['core_ai\aiactions\\' . $actionname] = [
                    'enabled' => (bool) ($legacy[$actionname] ?? true),
                    'settings' => [
                        'model' => $legacy["action_{$actionname}_model"] ?? $model,
                        'endpoint' => $legacy["action_{$actionname}_endpoint"] ?? $endpoint,
                        'temperature' => $legacy["action_{$actionname}_temperature"]
                            ?? \aiprovider_groq\abstract_processor::DEFAULT_TEMPERATURE,
                        'systeminstruction' => $legacy["action_{$actionname}_systeminstruction"]
                            ?? get_string("action_{$actionname}_instruction", 'core_ai'),
                    ],
                ];
            }

            $record = new stdClass();
            $record->name = get_string('pluginname', 'aiprovider_groq');
            $record->provider = \aiprovider_groq\provider::class;
            $record->enabled = (int) (bool) ($legacy['enabled'] ?? 0);
            $record->config = json_encode($instanceconfig);
            $record->actionconfig = json_encode($actionconfig);

            $DB->insert_record('ai_providers', $record);
        }

        // Drop the settings that are now held on the provider instance, plus the ones for
        // features this plugin no longer offers.
        $obsolete = [
            'apikey', 'orgid', 'enabled', 'enableglobalratelimit', 'globalratelimit',
            'enableuserratelimit', 'userratelimit',
            'generate_text', 'action_generate_text_enabled', 'action_generate_text_model',
            'action_generate_text_endpoint', 'action_generate_text_temperature',
            'action_generate_text_systeminstruction',
            'summarise_text', 'action_summarise_text_enabled', 'action_summarise_text_model',
            'action_summarise_text_endpoint', 'action_summarise_text_temperature',
            'action_summarise_text_systeminstruction',
            'generate_image', 'action_generate_image_enabled', 'action_generate_image_model',
            'action_generate_image_endpoint',
        ];
        foreach ($obsolete as $setting) {
            unset_config($setting, 'aiprovider_groq');
        }

        upgrade_plugin_savepoint(true, 2026082500, 'aiprovider', 'groq');
    }

    return true;
}
