# Contributing

## Scope
This repository contains the Moodle AI provider plugin `aiprovider_groq`.

## Versioning
- Public releases use Semantic Versioning and Git tags: `vMAJOR.MINOR.PATCH`.
- Current release line starts at `v1.0.0`.
- Moodle plugin upgrade version remains numeric in `version.php` (`$plugin->version`) and must increase for every release.
- Human-readable release version is defined in `version.php` as `$plugin->release`.

## Release checklist
1. Implement and test changes.
2. Update `CHANGELOG.md` under an unreleased section, then finalize release notes.
3. Increase `version.php`:
   - `$plugin->version`: bump numeric Moodle version.
   - `$plugin->release`: set SemVer tag (for example `v1.0.1`).
4. Commit changes and create a Git tag matching `$plugin->release`.
5. Push branch and tags.

## Development expectations
- Follow Moodle coding style and plugin architecture.
- Keep changes small and focused.
- Add or update tests under `tests/` for behavior changes.
- Document user-visible behavior changes in `README.md` and `CHANGELOG.md`.

## Pull request guidance
- Use clear commit messages.
- Describe what changed, why it changed, and how it was tested.
- Include screenshots only when UI/configuration behavior changes.

