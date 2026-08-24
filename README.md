# Schoolees Groq AI Provider (aiprovider_groq)

## Summary
Adds Groq Cloud as an AI provider for Moodle's AI subsystem, using Groq's OpenAI-compatible
Chat Completions API. It supports the three text actions Moodle ships: **generate text**,
**summarise text** and **explain text**.

Current release: **v1.1.0**

## Requirements
- Moodle **5.0+** (the AI subsystem uses per-instance provider configuration)
- A Groq API key — https://console.groq.com/keys

## Installation
### Option A: Install from ZIP
1. Site administration → Plugins → Install plugins
2. Upload the plugin ZIP
3. Complete the upgrade

### Option B: Install by copying the folder
Copy this plugin into your Moodle codebase at:

```
<your-moodle-root>/ai/provider/groq
```

Then run the Moodle upgrade.

### Release packaging (for Moodle.org)
- The plugin directory name must be `groq`.
- The release ZIP root must contain that `groq/` directory (not `aiprovider_groq/`).
- Expected install path after extraction: `<moodle-root>/ai/provider/groq`.

## Configuration
1. Go to **Site administration → AI → AI providers**
2. Add an instance of **Schoolees Groq AI provider**
3. Enter your **Groq API key**, then enable the instance

Rate limiting (site-wide and per-user) is provided by Moodle core on the same form.

### Action settings
**Site administration → AI → AI providers → (your Groq instance) → Actions**

Each action can be configured with:

| Setting | Default | Notes |
| --- | --- | --- |
| Model | `llama-3.1-8b-instant` | Any model your key can use — see https://console.groq.com/docs/models |
| API endpoint | `https://api.groq.com/openai/v1/chat/completions` | Change only when routing through a compatible proxy |
| Temperature | `0.2` | 0–2 |
| System instruction | Moodle's per-action default | |

**Summarise text** has two extra settings, because models routinely ignore length and
formatting instructions:

| Setting | Default | Notes |
| --- | --- | --- |
| Maximum words in a summary | `500` | Applied to the response as well as requested in the prompt. Cuts back at a sentence boundary where possible. Set to `0` to leave the summary untouched. |
| Force a single paragraph | Enabled | Removes line breaks and bullet points from the response. |

New provider instances are created with these defaults already filled in, so the provider
works as soon as an API key is saved.

### Image generation
Groq Cloud has no image generation API, so this plugin does **not** register the
`generate_image` action. Use another provider (for example the bundled OpenAI provider)
for image generation.

## Testing / troubleshooting
### `test_connection.php`
An admin-only diagnostic page that runs one **generate text** request through Moodle's AI
subsystem and reports what came back.

1. Configure and enable a Groq provider instance.
2. Log in as a site administrator.
3. Open `https://<your-moodle-site>/ai/provider/groq/test_connection.php`
4. Press **Make a test request**.

The request is only sent on an explicit, session-key protected form submission — loading
the page does not spend API quota. The page requires `moodle/site:config` and reports the
HTTP status and error message when the request fails.

## Privacy
When enabled, this provider sends the following to Groq:

- The **prompt text** for the requested action (for example the text being summarised)
- The configured **system instruction**, **model** and **temperature**
- A **one way hash** of the site identifier and the Moodle user id, so requests can be
  attributed without disclosing who made them

Groq may store and retain this data according to your Groq account settings. This plugin
stores nothing itself.

## Third-party libraries
This plugin bundles no third-party PHP or JS libraries. It uses Moodle core APIs only.

## Upgrading from the Moodle 4.5 era version
Moodle 5.0 replaced site-wide AI provider settings with provider instances, and core only
migrates its own bundled providers. This plugin's upgrade step carries any Moodle 4.5 era
`aiprovider_groq` settings across to a provider instance, then removes the obsolete
settings. The migrated instance keeps its previous enabled state, so check
**Site administration → AI → AI providers** after upgrading.

## Support / Issues
- Issue tracker: https://github.com/Schoolees/schoolees.moodle-aiprovider-groq/issues
- Upstream reference: https://github.com/marcusgreen/moodle-aiprovider_groq

## Versioning
- Releases and tags use Semantic Versioning (for example `v1.1.0`).
- Moodle upgrade versioning uses the numeric `$plugin->version` field in `version.php`.
- See `CHANGELOG.md` for release history.

## Contributing
See `CONTRIBUTING.md` for the contribution and release workflow.

## License
GPL v3 or later (same as Moodle). See `LICENSE`.

## Credits
- Original author: Marcus Green
- Schoolees fork: branding and Moodle 5+ fixes
