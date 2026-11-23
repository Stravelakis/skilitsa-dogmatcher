# Using DogMatcher

## What visitors see

The quiz shows questions one by one, each with a scale and short labels for both ends. At the end a podium shows the three best-matching breeds with gold, silver and bronze medals, and a short explanation for each (a button that opens and closes, also by keyboard). Further breeds are listed below. Each result has its own address, `/match/<token>/`, so it can be shared and reloaded.

If an answer is a dealbreaker for the visitor, breeds that conflict with it are excluded and the conflicting trait is named.

## Put the quiz on a page

Add the Skilitsa Matcher block, or use the shortcode:

```
[skilitsa_matcher]
```

## The admin

A **Skilitsa** menu appears in the WordPress sidebar with five screens, and a **Settings** link beside Deactivate on the Plugins page.

| Screen | What it is for |
|---|---|
| Settings | The nine engine settings, each with its default and one sentence of help. |
| Traits | Every trait's `penalty_over`, `penalty_under` and `global_weight`. |
| Breed data | Every breed, how many traits it covers, and which are missing. |
| Breed posts | The breed post type. |
| Import | Fetch the sheet, see the differences, then apply. |

The admin is in English. The public quiz uses whatever language your sheet's texts are in.

## Your Google Sheet

The sheet needs eight tabs: `SETTINGS`, `UI_TEXTS`, `SECTIONS`, `TRAITS`, `QUESTIONS`, `MAPPING`, `RESULT_TEMPLATES` and `BREEDS`. Each tab has required columns (for example `key` and `value` on SETTINGS, `breed_key` on BREEDS). Anyone with the link needs to be able to view it. On the Import screen you point the plugin at your sheet, review the plan, and apply it. A plan with errors shows no apply button.

## Optional: AI drafts

Admin only, and off until you configure it. Add three constants to `wp-config.php`, above the line that says `That's all, stop editing!`:

```php
define( 'SKILITSA_AI_BASE_URL', 'https://your-provider.example/v1' );
define( 'SKILITSA_AI_MODEL',    'your-model' );
define( 'SKILITSA_AI_KEY',      'your-key' );
```

Keys go in `wp-config.php`, never in the database, because a key stored in the database travels in every backup. The AI can only produce drafts. A person approves each one through the same form they would have typed into.
