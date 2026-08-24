# Changelog

All notable changes to this project are documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [1.1.0] - 2026-08-25
### Fixed
- Action settings (model, endpoint, temperature, system instruction) were silently ignored.
  Moodle 5 keys the provider action config by the fully qualified action class name, but the
  processors looked it up by the short action name, so every request used the hard-coded
  defaults no matter what an administrator configured.
- The PHPUnit suite could not run at all: the provider was constructed with `description:` and
  `model:` named arguments that `\core_ai\provider` does not accept in Moodle 5.
- A newly created provider instance had no model or endpoint until the action settings form was
  opened and saved. `get_action_setting_defaults()` is now implemented.
- Rate limiting could not be configured. The plugin overrode `is_request_allowed()` to read
  site-wide plugin config that Moodle 5 has no UI for. Core's implementation, driven by the
  rate limit fields already on the provider instance form, is now used instead.
- Responses missing optional OpenAI fields (`system_fingerprint`, `usage`, `id`) no longer raise
  undefined property warnings.
- A non-JSON error body (for example an HTML page from a proxy) is truncated instead of being
  returned in full as the error message.
- `test_connection.php` now requires an explicit, session-key protected submission before
  spending API quota, escapes the model's response, and reports failures instead of printing
  nothing.
- Language strings are in alphabetical order and use the `_help` suffix Moodle expects, so the
  form help buttons work.
- `$plugin->supported` declared `599`, which is not a real Moodle branch number.

### Added
- Support for the `explain_text` action introduced in Moodle 5.0.
- Configurable summary guardrails: maximum words (0 disables) and force single paragraph.
  Truncation now cuts at a sentence boundary where possible instead of mid-sentence.
- `db/upgrade.php` migrates Moodle 4.5 era plugin settings into a provider instance, since core
  only migrates its own bundled providers.
- Test coverage for `explain_text`, the summary guardrails, partial API responses and
  authentication headers.

### Removed
- The `generate_image` action. Groq Cloud has no image generation API, and the shipped defaults
  pointed at `https://api.openai.com/v1/images/generations`, which would have sent the Groq API
  key to OpenAI.
- The `orgid` setting. It was sent as the `OpenAI-Organization` header, which Groq ignores.
- `classes/compat.php`, a runtime `include_path` hack for system PEAR. The API key field now
  uses Moodle's standard `passwordunmask` element, as core's own providers do.
- `settings.php`. The `aiprovider` plugin type never loads it, so the file was dead code.

## [1.0.0] - 2026-02-17
### Added
- Initial stable SemVer release for the Schoolees Groq AI Provider.
- Documented contribution workflow in `CONTRIBUTING.md`.
- Established changelog-based release tracking.

