---
checkedAgainst: v2.0.0
checkedOn: 2026-11-21
---

## Orientation

Skilitsa DogMatcher is **two WordPress plugins**, both at version `2.0.0`, both `GPL-3.0-or-later` in their headers. Requires WordPress 6.4+ and PHP 8.1+. No runtime dependencies; PHPUnit 10.5 is a dev dependency only. Both `composer.json` files still say `"license": "proprietary"`, which disagrees with the headers (logged in HANDOFF).

| Plugin | Role |
|---|---|
| `plugins/skilitsa-core` | Data, scoring engine, admin, import, AI drafts. No public UI. Registers the breed custom post type. |
| `plugins/skilitsa-matcher` | The public quiz (shortcode and block), four REST routes, the `/match/<token>/` result page, anonymous session storage. Needs Core 2.0.0 or newer. |

Rules that decide arguments:

1. **The browser never scores.** The client sends answers and renders what comes back. The breed matrix never reaches the page.
2. **The engine has no WordPress in it.** `src/Engine` takes plain value objects and returns a `MatchResult`; a test (`NoWordPressDependencyTest`) fails if WordPress code appears there.
3. **No generated word reaches a visitor.** A test (`NoFrontendReachTest`) fails if the public plugin or the engine mentions the `Skilitsa\Core\Ai` namespace.
4. **Bad input falls back, it does not vanish.** Out-of-range settings are clamped, unreadable settings fall back to defaults, and an import with errors cannot be applied.

> **Watch out** This page describes the code at version **2.0.0** on `main`. No release tag or GitHub release exists yet (checked 21 Nov 2026), so the review stamp uses the version from the plugin headers. If this page and the code disagree, the code wins; the engineering notes are in [HANDOFF.md](https://github.com/Stravelakis/skilitsa-dogmatcher/blob/HEAD/HANDOFF.md).

## Layout

```
plugins/skilitsa-core/
  skilitsa-core.php          header, version const, activation hook, boot on plugins_loaded
  src/Engine/                MatchEngine, UserTraitCalculator, DealbreakerEvaluator, BreedScorer,
                             TraitCostCalculator, VerdictClassifier, MatchComparator, Value/*
  src/Data/                  Settings, Schema, Installer, TraitDefaults, BreedKey, Wpdb/* sources
  src/Csv/ src/Import/       CsvParser, SheetSchema, ImportPlanner, ImportExecutor, ImportReporter
  src/Migration/             MigrationPlanner/Executor (used by bin/migrate.php)
  src/WordPress/             Plugin, BreedPostType, BreedQueryBridge, BreedPostFinder
  src/Admin/                 AdminMenu, Screen/*, Form/*, View/*, templates/*
  src/Ai/                    TaskRegistry, TaskRunner, Client/*, Task/*, Draft/*
  src/MatchService.php       the one entry point the matcher calls
  tests/Unit  tests/Golden   PHPUnit
plugins/skilitsa-matcher/
  skilitsa-matcher.php       header, Core version check, activate/deactivate hooks
  src/Frontend/              QuizShortcode, QuizBlock, ResultsRoute, Assets
  src/Rest/                  MatchController, RateLimiter
  src/Session/               MatchSession, SessionRepository, Token
  src/Content/               ContentProvider (reads questions, breeds, copy out of Core's tables)
  src/Copy/ src/View/        ExplanationPicker, ResultViewModelBuilder, BreedCard, TraitLine
  src/Telemetry/             EventRecorder
  assets/                    matcher.js (client, no build step), matcher.css, block.js (editor)
  templates/results.php      the /match/<token>/ page
bin/build-release.sh         builds build/skilitsa-core.zip and build/skilitsa-matcher.zip
legacy/v1/                   the old plugin, reference only
docs/ai-configuration.md     AI setup notes
```

## Data flow

1. **Import (admin).** `ImportScreen` fetches eight tabs from `https://docs.google.com/spreadsheets/d/<id>/gviz/tq?tqx=out:csv&headers=1&sheet=<TAB>` with `wp_remote_get` (30 second timeout), parses them with `CsvParser`, and `ImportPlanner` builds an `ImportPlan` of errors and warnings. **Fetch and preview** writes nothing. **Apply this import** runs `ImportExecutor::apply` inside one transaction (`START TRANSACTION` ... `COMMIT`, `ROLLBACK` on any throw), and only when `isSafeToApply()` (no errors, and the plan has content).
2. **Quiz load (browser).** `matcher.js` finds `[data-skilitsa-matcher]`, calls `GET /wp-json/skilitsa/v1/match/questions`, and renders one question per screen.
3. **Score.** On the last question the client posts to `POST /match/score`. `MatchController` builds an `AnswerSet`, runs `MatchService` (Core), builds the view model, stores the session, answers, top-10 ranks and a JSON snapshot, records a `quiz_completed` event, and returns `{token, resultUrl, result}`.
4. **Share.** `/match/<token>/` (a rewrite rule, `^match/([a-z0-9]{12})/?$`) renders `templates/results.php`, whose client fetches `GET /match/<token>`. A stored snapshot is returned as is; with no snapshot the route recomputes from the saved answers (so scores can differ if data changed).

### Sheet contract

Eight required tabs: `SETTINGS`, `UI_TEXTS`, `SECTIONS`, `TRAITS`, `QUESTIONS`, `MAPPING`, `RESULT_TEMPLATES`, `BREEDS`. Required columns per tab are in `SheetSchema` (for example `key,value` on SETTINGS, `question_key,trait_key` on MAPPING, `breed_key` on BREEDS). A tab that cannot be fetched is an error, not a smaller import. `headers=1` is mandatory in the export URL: without it gviz folds leading data rows into the header.

- `MAPPING.answer_value`, `answer_label`, `notes`, `placeholders` are **ignored on purpose** (`SheetSchema::IGNORED_COLUMNS`). v1 joined on `answer_value`, which was never filled in, so every breed scored 0.
- Direction spellings `inverse`, `invert`, `inverted`, `reverse`, `reversed`, `opposite` mean inverse; blank, `same`, `direct`, `normal` mean same; anything else warns and falls back to same.
- Breed trait values outside 1 to 5 are clamped with a warning. The default sheet's `QUESTIONS.quick_mode` (truthy: `1,true,yes,y,x`) marks `tier = core`; everything else is `extended`.
- `show_dealbreaker` and `show_ignore` are sheet checkboxes: blank means **false** (Google exports unticked as an empty string), the accepted true spellings are `1,true,yes,y,x,on`, and only a missing column falls back to true.
- **Penalties and global weights are not in the sheet.** `penalty_over` and `penalty_under` come from `TraitDefaults::PENALTIES` (24 reviewed rows; an unknown trait gets 1.0 and 1.0), and `global_weight` is 1.0. A trait edited on the **Traits** screen gets `is_tuned = 1` and keeps its three numbers through every later import.
- Breed values are written **only for breeds that already have a published `skilitsa_breed` post** whose canonical slug matches (`BreedKey::canonical`: lowercase, underscores to hyphens, a percent-encoded Greek tail after an ASCII head dropped). Other breeds are skipped at write time and are not named in the preview. Creating posts is the migration's job (see **bin/migrate.php** below).

The default sheet ID is `1Du1Lod48C0naHt6NWUc1DP_5BAf9st90cKDDsUdwpY0` (`Settings::DEFAULT_SHEET_ID`). On 21 Nov 2026 its tabs held 30 questions, 24 traits, 38 mapping rows (34 same, 4 invert) and 257 breeds. Those are sheet contents, not code: they change when the sheet does.

## The 30 questions and the mapping

Questions are rows in `QUESTIONS`, each with a `weight` (default 1.0; on the default sheet 0.7 to 1.2), a 1 to 5 scale with end labels, and optional helper text. `MAPPING` links a question to a trait with a `direction` and a `weight` (read from `weight_override`, default 1). One question can feed several traits and one trait can be fed by several questions. On the default sheet, 4 of the 38 links are inverse.

Per question the visitor gives a 1 to 5 answer and may tick **dealbreaker** or **ignore** (the two buttons render only where the sheet's `show_dealbreaker` and `show_ignore` allow it). Default button texts are Greek (`Το θέλω οπωσδήποτε`, `Δεν μου κάνει διαφορά`) and come from `UI_TEXTS` when present. A terms checkbox (label from `UI_TEXTS.terms_checkbox`) sits with the questions; **Next** is blocked until it is ticked, and the terms version `2026-08-21` is stored with the session when the client sends `termsAccepted`.

## Scoring, exactly

All of it is in `src/Engine`. Defaults are in `ScoringConfig`; the six values the admin can change are mapped by `Settings::scoringConfigFrom`.

### 1. Answers to a trait profile (`UserTraitCalculator`)

For every link whose question has an answer (an ignored question has none):

- `w = question.weight x link.weight`; links with `w <= 0` are skipped.
- `value = answer`, or `6 - answer` when the link is inverse.
- Per trait: `profile[trait] = sum(value x w) / sum(w)`, clamped to 1..5.

A trait with no contributing answer is absent from the profile. `profile.count()` is the number of traits present; call it **n**.

### 2. Dealbreakers (`DealbreakerEvaluator`)

- **Salient traits:** every trait linked to a question the visitor marked as a dealbreaker and did not ignore.
- **Violation:** for a salient trait where both the profile and the breed have a value, `rawCost >= dealbreaker_threshold` (default **2.0**) excludes the breed. The violated trait keys are sorted and each is counted once.

### 3. Cost of one trait (`TraitCostCalculator`)

```
over   = max(0, breed - user)         under = max(0, user - breed)
raw    = over x penalty_over + under x penalty_under
maxFor = max(0, penalty_over x (5 - user), penalty_under x (user - 1))
norm   = clamp(raw / maxFor, 0, 1)
```

A trait with `maxFor = 0` cannot discriminate for this visitor (for example shedding, 1.0 / 0.0, for someone who answered 5) and is **skipped**, so it cannot pull the average toward 100.

### 4. Score of one breed (`BreedScorer`)

For each trait in the profile, skip it when the trait definition or either value is missing, when it cannot discriminate, or when its weight is 0. Otherwise:

```
weight   = global_weight x salience_multiplier (default 2.0) if salient, else global_weight
cost    += norm ^ gamma x weight                 gamma default 0.7
weighted = cost / sum(weight)
raw      = 100 x (1 - weighted)                  (50 if no trait was used)
final    = (n x raw + m x 50) / (n + m)          m = shrinkage_m, default 1.0
score    = round(clamp(final, 0, 100))           integer
```

`n` is the visitor's profile size, not the number of traits that happened to discriminate: someone who answered everything is not penalised for a flat matrix.

> **Try it** A visitor wants outdoor energy 2 (`penalty_over` 1.5, `penalty_under` 0.5) and a breed has 4. Raw cost is 2 x 1.5 = 3.0. The worst possible for this visitor is max(1.5 x 3, 0.5 x 1) = 4.5, so norm = 0.667 and `0.667 ^ 0.7` is about 0.753. With n = 10, raw 60 and m = 1 the final score is (10 x 60 + 50) / 11 = 59.09, shown as 59. This example is worked from the formulas above, not read from a test.

### 5. Verdicts (`VerdictClassifier`)

Per trait, from the raw cost: `<= 0.5` aligned, `<= 1.5` neutral, `<= 2.5` pitfall, higher opposed; a violated dealbreaker is always opposed. These three thresholds are `ScoringConfig` defaults and **are not exposed as settings**. A result card shows up to 2 aligned lines and 1 pitfall line; neutral is deliberately silent.

### 6. Ranking and tie-breaking (`MatchEngine::sortMatches`)

Highest `score` first; ties go to the **lower full-precision `weightedCost`**; remaining ties go to **`strcmp` on the breed key**. Excluded breeds are scored too and kept in a separate list, sorted the same way. The podium is the first 3 ranked; the runners-up shown are the next 7.

- `hasEnoughEvidence = n >= min_traits_for_results` (default **8**). If false, no breeds are shown, only the disclaimer.
- `isCloseCall`: at least 3 ranked and `score[0] - score[2] <= close_call_points` (default **5**).
- `blockingDealbreaker`: set only when **no** breed survives; the trait that excluded the most breeds (ties: key ascending).

### Determinism

There is no randomness in scoring, and none in ranking. The only variable text is the explanation wording: `ExplanationPicker` chooses between variants `_1` and `_2` with `crc32(breedKey + traitKey) % count`, so the same breed always gets the same sentence. Randomness exists in exactly one place, the result **token** (`random_int`). A stored result replays its JSON snapshot rather than recomputing, so it also survives later data edits.

## Settings

Stored in the option `skilitsa_core_settings` (autoload off). `Settings::sanitize` clamps to these bounds; non-numeric input is ignored.

| Key | Default | Bounds | Used by |
|---|---|---|---|
| `gamma` | 0.7 | 0.1 to 5 | engine |
| `shrinkage_m` | 1.0 | 0 to 20 | engine |
| `salience_multiplier` | 2.0 | 1 to 5 | engine |
| `dealbreaker_threshold` | 2.0 | 0.5 to 4 | engine |
| `close_call_points` | 5 | 0 to 25 | engine |
| `min_traits_for_results` | 8 | 1 to 24 | engine |
| `results_top_cards` | 3 | 1 to 5 | **nothing** |
| `results_more_count` | 7 | 0 to 50 | **nothing** |
| `flow_mode` | `all` | `all`, `live`, `tiered` | matcher (see below) |
| `accent_color` | `#2f6f4f` | 6-digit hex | matcher CSS variable `--sk-accent` |
| `sheet_id` | the default sheet | ID or full URL (the ID is extracted) | import |

> **Watch out** `results_top_cards` and `results_more_count` are shown on the Settings screen but no code reads them: the podium is 3 (`PODIUM_SIZE`) and the runners-up are 7 (the default argument of `ResultViewModelBuilder::build`). Likewise only `tiered` changes behaviour: it limits `/match/questions` to `tier = core` rows. The client never reads `flowMode`, so `all` and `live` behave the same. Both are logged in HANDOFF.

If the stored combination is rejected by `ScoringConfig`, the whole set falls back to defaults. The environment variable `SKILITSA_SHEET_ID` overrides the sheet ID for the Import screen.

## REST routes

Namespace `skilitsa/v1`. All have `permission_callback: __return_true` (the quiz must work logged out) and all are rate-limited.

| Route | Method | What |
|---|---|---|
| `/match/questions` | GET | Questions for the active flow, `uiTexts`, `flowMode`, and the terms label and version. |
| `/match/score` | POST | Body `{answers:{Q01:1..5}, dealbreakers:[], ignored:[], token?, termsAccepted?}`. Each answer must be numeric 1 to 5, else `400 skilitsa_bad_answers`. A valid existing `token` is reused, otherwise a new one is made. |
| `/match/event` | POST | Body `{token, type, target}`. Ignored unless the token is well formed and `type` is in `EventRecorder::ALLOWED_EVENTS`. |
| `/match/<token>` | GET | The stored result, `404` if the token is unknown. |

`RateLimiter`: 60 requests per 60 seconds per bucket (`questions`, `score`, `event`, `result`) per client. The client key is `HMAC-SHA256(address, wp_salt('nonce'))` cut to 16 characters, kept in a transient; the raw address is never stored. The address is the first value of `HTTP_CF_CONNECTING_IP`, else `HTTP_X_FORWARDED_FOR`, else `REMOTE_ADDR`.

> **Watch out** Those forwarded headers are read as given. On a server that is not behind a proxy that overwrites them, a client can send its own and sidestep the limit.

Allowed event types: `quiz_started`, `question_answered`, `quiz_completed`, `card_clicked`, `breed_link_clicked`, `more_results_opened`, `excluded_opened`, `dealbreaker_relaxed`. The client script calls `track()` with `card_clicked`, `excluded_opened`, `explanation_opened` and `restart`; the last two are not on the allowed list and are dropped silently.

## What is stored, and what leaves the browser

**From the visitor's browser** the only requests are to your own site's REST routes above. `matcher.css` has no external URLs. The only browser storage touched is a read of `localStorage['skilitsa_reading_prefs']` (text size and spacing), and the script never writes it.

**Stored on your server** (tables `<prefix>skilitsa_match_sessions`, `match_answers`, `match_results`, `match_events`): a random token, the answers with their dealbreaker and ignore flags, the flow mode, the terms version and time when accepted, start and finish times, the result snapshot, the top-10 ranks and scores, and event rows (type, target cut to 191 characters, time). The plugin records **no IP address, no user agent and no user ID**; `user_uuid` exists as a column but nothing sets it. Your web server's own access log is outside the plugin.

**Retention:** a daily scheduled event deletes sessions older than 90 days (and their answers, results and events) where `user_uuid IS NULL`, which is all of them. The event is scheduled at activation (`wp_schedule_event`).

**Result pages** send `no-store` cache headers and `X-Skilitsa-No-Cache`, define `DONOTCACHEPAGE`, fire `litespeed_control_set_nocache`, and add `noindex, nofollow` through `wp_robots`. A theme can override the template at `skilitsa-matcher/results.php`.

**Outbound from the server:**

- `docs.google.com`, on the admin's Import (and the `bin/migrate.php` run). No visitor data.
- The AI endpoint **you** configure, only when an admin runs a task. The tasks work on the trait matrix, questions and copy; no task reads the session tables (checked by search, not by test).

## Admin

Capability `manage_options` everywhere. Menu **Skilitsa** (icon `dashicons-pets`, position 58) with **Settings**, **Traits**, **Breed data**, **Breed posts** (the post type's own list), **Import** and **AI drafts**. A **Settings** link is added beside Deactivate on the Plugins page. Forms are checked with `check_admin_referer` and a nonce named per screen (`skilitsa_import`, `skilitsa_settings`, `skilitsa_trait_matrix`, `skilitsa_breed_values`, `skilitsa_ai_drafts`).

A test walks `AdminMenu::SCREENS` and fails when a screen has no help text in `ScreenHelp`.

## AI drafts

Six tasks in `TaskRegistry`: `matrix_qa`, `draft_breed_traits`, `check_penalties`, `translate`, `alt_text`, `question_audit`. Temperature is fixed at 0. Nothing is configured by default (empty base URL, model and key); an unconfigured task reports itself and makes no call.

Each setting resolves in order: `SKILITSA_AI_<SETTING>_<TASK>`, then `SKILITSA_AI_<SETTING>`, then the built-in default. The settings are `BASE_URL`, `MODEL`, `KEY` and `PROVIDER` (`openai`, the default, or `anthropic`). Keys live in `wp-config.php`, never in the database. `HttpAiClient` treats anything but a clean 200 with a JSON object as a failure with no data, and scrubs the key from error text. Output is stored as a draft; **Approve** sends it through the same form a person would type into, **Reject** changes nothing.

See [docs/ai-configuration.md](https://github.com/Stravelakis/skilitsa-dogmatcher/blob/HEAD/docs/ai-configuration.md) for the provider examples.

## Install from source

Each plugin loads `vendor/autoload.php` and shows an admin notice (instead of fataling) if it is missing. The `vendor/` folders are git-ignored, so a plain copy of `plugins/skilitsa-*` does not run until Composer has generated the loader:

```bash
cd plugins/skilitsa-core && composer install --no-dev --optimize-autoloader
cd ../skilitsa-matcher && composer install --no-dev --optimize-autoloader
```

`bin/build-release.sh` automates this and zips both plugins into `build/`. It expects `composer.phar` at the repo root, a PHP ini at `dev/php.ini` (override with `SKILITSA_PHP_INI`) and `python` for zipping, and it also copies `plugins/skilitsa-core/bin/migrate.php` into the Core zip. The `dev/` folder and `composer.phar` are not in the repository.

**Activation:** Core first, then Matcher. Matcher's activation flushes rewrite rules and schedules the purge; Core's creates the ten tables with `dbDelta` and writes the default settings. Core re-checks its schema version (`Schema::VERSION`, `1.3.0`) on every `init` and upgrades if behind.

**bin/migrate.php.** `wp eval-file plugins/skilitsa-core/bin/migrate.php` is a **dry run**; add `-- --apply-migration` and/or `-- --apply-import` to write. It exists for skilitsa.com's existing breed articles (migrate to breed posts, with redirects), then import. On a first run migrate before importing. How it behaves on another site is not confirmed.

There is no uninstall routine in v2: deleting the plugins leaves the tables and the options behind.

## Tests

```bash
bash plugins/skilitsa-core/test.sh                       # all
bash plugins/skilitsa-core/test.sh --testsuite unit
bash plugins/skilitsa-core/test.sh --testsuite golden
bash plugins/skilitsa-matcher/test.sh                    # unit only
```

PHPUnit runs from each plugin's `vendor/bin/phpunit` after `composer install` (the scripts call `composer.sh` for that). `test.sh` passes `-c dev/php.ini`; set `SKILITSA_PHP` and `SKILITSA_PHP_INI` if your PHP differs. The golden suite loads `tests/fixtures/golden/` (`traits`, `questions`, `links`, `breeds`, `cases`: 9 traits, 10 questions, 11 links, 6 breeds, 7 cases) and checks scoring and ranking against saved expectations. `plugins/skilitsa-core/bin/regenerate-golden.php` fills in the `expected` block of `cases.json` from the engine's current behaviour; use it only after a deliberate engine change and read the diff line by line.

> **Watch out** This page was written without a PHP install, so the test suites were **not run** while checking it.

## Gotchas

- **Import replaces, it does not merge.** `ImportExecutor` empties the questions, links, templates and `breed_traits` tables and rewrites them from the sheet. Values edited on the Breed data screen are lost on the next import; only `is_tuned` traits keep their penalties and weight.
- **The slug is the join.** A breed post whose slug does not canonicalise to the sheet's `breed_key` gets no data and is not named by the importer.
- **Few answers pull scores toward 50.** Shrinkage uses `n`, the number of traits the answers gave a value for. A visitor who skips many questions (ignore, or no answer) has a small `n`, so every score sits closer to 50. Below `min_traits_for_results` (8) nothing is shown at all.
- **Two exclusion paths.** A breed can be excluded by a dealbreaker even with a high raw score; it still carries a score in the "excluded" list.
- **No answers are required server-side** beyond the evidence minimum. A direct POST with 8 traits worth of answers scores fine.
- **Public text is Greek** unless the sheet's `UI_TEXTS` and `RESULT_TEMPLATES` say otherwise; the fallbacks compiled into `matcher.js` and `MatchController` are Greek.
- **Cron.** If WordPress cron is disabled and nothing else triggers it, the 90 day purge does not run.
