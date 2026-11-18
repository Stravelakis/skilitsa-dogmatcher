# Skilitsa DogMatcher

**A dog-breed matching quiz for WordPress.** Visitors answer questions about their life; the plugin scores every breed against the answers and shows the three best matches, with a plain reason for each.

Built for [skilitsa.com](https://skilitsa.com), a Greek dog-owner community. The quiz and the original data are in Greek; the admin is in English.

> Docs (once published): https://docs.stravelakis.com/skilitsa-dogmatcher/

## What it does

- **Quiz and podium.** Questions one by one, then gold, silver and bronze breeds with explanations. Each result has its own link, `/match/<token>/`.
- **Your data in a Google Sheet.** Traits, questions, breeds and texts. An Import screen shows the differences before anything is applied.
- **Dealbreakers.** An answer a visitor cannot live with excludes conflicting breeds and names the trait.
- **Accessible.** Real fieldsets and progress bar, keyboard-friendly explanations, reduced motion respected.
- **A real admin.** Settings, Traits, Breed data, Breed posts and Import, each with help.
- **Optional AI drafts.** Admin-only; a person approves every draft. Nothing generated reaches a visitor on its own.

## Two plugins

| Plugin | What it is |
|---|---|
| `plugins/skilitsa-core` | Data and scoring engine, admin, import, AI drafts. No public UI. |
| `plugins/skilitsa-matcher` | The public quiz (block and `[skilitsa_matcher]` shortcode) and the results page. Needs Core. |

`legacy/v1` holds the old plugin for reference only.

## Requirements

WordPress 6.4+ and PHP 8.1+. A Google Sheet with eight tabs: `SETTINGS`, `UI_TEXTS`, `SECTIONS`, `TRAITS`, `QUESTIONS`, `MAPPING`, `RESULT_TEMPLATES`, `BREEDS`.

## Install

See [INSTALL.md](INSTALL.md). Usage is in [GUIDE.md](GUIDE.md).

## Tests

PHPUnit, from each plugin folder: `./test.sh`.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
