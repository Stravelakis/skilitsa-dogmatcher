# HANDOFF

## 2026-11-21: docs site rebuilt (v2 theme, three reading levels)

**What changed (docs only, in `site/`):** master docs theme v2 (layout, components, scripts, styles, sync script, package.json with the three font packages); new front page; Dev, English and ELI5 pages and the glossary rewritten from the code; review stamp `checkedAgainst: v2.0.0`, `checkedOn: 2026-11-21`. The first headings of INSTALL.md and GUIDE.md were renamed (to "Install DogMatcher" and "Using DogMatcher") because "Install" and "Guide" produced page ids that clashed with the site's own section ids.

**Not documented / not available:**
- No release tag or GitHub release exists, so the stamp uses the plugin header version 2.0.0.
- No screenshots: there is no running WordPress instance to capture, so the front page has no carousel.
- Heads-up for the owner: the Tally feedback link (`feedbackUrl`) is empty.

**Product problems found (not fixed):**
1. Settings `results_top_cards` and `results_more_count` are shown in the admin but nothing reads them (podium is fixed at 3, runners-up default 7).
2. Question flow: only `tiered` has an effect (core questions only). `live` and `all` behave the same; the client never reads `flowMode`. The label "Core questions first, the rest after" is not what happens: the rest never appear.
3. The client sends events `explanation_opened` and `restart`; the server's allowed list does not contain them, so they are dropped.
4. `composer.json` in both plugins says `"license": "proprietary"`; plugin headers and LICENSE say GPL-3.0-or-later.
5. `bin/build-release.sh`, `composer.sh` and `test.sh` expect `dev/php.ini` and `composer.phar`, which are not in the repo; the release script also points to `docs/RELEASE-RUNBOOK.md`, which does not exist.
6. INSTALL.md says to copy the plugin folders; they then show "dependencies are missing" until Composer generates `vendor/autoload.php`. It also says "run tests from each plugin folder" without the Composer step.
7. GUIDE.md says "nine engine settings"; the Settings screen has eleven fields (six engine ones).
8. GUIDE.md says five admin screens; the menu has six (AI drafts).
9. No uninstall routine in v2: deleting the plugins leaves tables and options.
10. Import writes breed values only for breeds that already have a published breed post; the others are skipped without being named in the preview.
11. Rate limiter trusts `CF-Connecting-IP` / `X-Forwarded-For` as given.
12. The close-call help text says "instead of crowning a winner"; the page still shows the podium with a note.

**Claims not confirmed:**
- PHP is not installed on the machine used, so PHPUnit was not run.
- `bin/migrate.php` behaviour on a site other than skilitsa.com.
- Counts on the default Google Sheet (30 questions, 24 traits, 38 mapping rows, 257 breeds) come from fetching the public sheet on 2026-11-21, not from code.
- That no AI task reads visitor session tables was checked by search, not by a test.
