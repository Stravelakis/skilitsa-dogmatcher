---
# Not reviewed yet: owner ticks templates/docs/REVIEW-CHECKLIST.md, then adds
# checkedAgainst: v2.0.0 and checkedOn: YYYY-MM-DD here.
---

## Skilitsa DogMatcher: developer view

Two WordPress plugins, version `2.0.0`, both GPL-2.0-or-later in their headers at the time of writing. Requires WordPress 6.4+ and PHP 8.1+. No runtime dependencies; PHPUnit 10.5 is a dev dependency only.

- **`skilitsa-core`:** data and engine, no public UI. `src/Engine` (scoring, with zero WordPress code; a test enforces it), `src/Data`, `src/Csv` (a CSV parser that survives Google's exports), `src/Import`, `src/Migration`, `src/WordPress`, `src/Admin`, `src/Ai`.
- **`skilitsa-matcher`:** the public quiz. Requires Core. Shortcode `[skilitsa_matcher]` and a block. Results at `/match/<token>/`, with `noindex` and no-store caching headers.
- **Scoring:** nine engine settings (for example `gamma`, `shrinkage_m`, `dealbreaker_threshold`) edited in the admin. Traits carry `penalty_over`, `penalty_under` and `global_weight`. Flow modes: `all`, `live`, `tiered`.
- **Sheet contract:** eight tabs: SETTINGS, UI_TEXTS, SECTIONS, TRAITS, QUESTIONS, MAPPING, RESULT_TEMPLATES, BREEDS. The export URL needs `headers=1`. The MAPPING `answer_value` column is ignored on purpose.
- **Tests:** `plugins/skilitsa-core/test.sh` and `plugins/skilitsa-matcher/test.sh` (PHPUnit). Golden-file fixtures live in `plugins/skilitsa-core/tests/fixtures/golden/`.
- **AI layer:** admin-only drafts, configured by constants in `wp-config.php` (`SKILITSA_AI_BASE_URL`, `SKILITSA_AI_MODEL`, `SKILITSA_AI_KEY`). Nothing is configured by default. A test fails if the scoring engine or the public plugin touches the AI namespace.
- **Privacy:** the result page does not inline the breed matrix. Telemetry is anonymous.
