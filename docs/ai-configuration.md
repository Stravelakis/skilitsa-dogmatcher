# Configuring the AI tasks

Admin-side only. **No generated word ever reaches a visitor** — a task can only
produce a draft, and a person approves it through the same form they would have
typed into.

Nothing is configured out of the box. No endpoint, no model, no provider. Until
`wp-config.php` says otherwise every task reports itself unconfigured, the
screen says so, and nothing calls out.

**Keys go in `wp-config.php`, never in the database.** A key in an option
travels in every backup and in every copy pulled down to staging, which is how
a production key ends up on a laptop.

---

## Google AI Studio and Mistral

Both are free to start and both speak the OpenAI chat format, which is the
plugin's default, so no provider setting is needed for either.

Get the keys from:

- **Google AI Studio** — <https://aistudio.google.com/apikey>
- **Mistral** — <https://console.mistral.ai/api-keys>

Then, in `wp-config.php`, **above** the line that says
`/* That's all, stop editing! */`:

```php
/* ---- Skilitsa AI ------------------------------------------------------ */

// Google AI Studio for the reasoning work: reading the whole matrix, arguing
// with the penalties, auditing the questions.
define( 'SKILITSA_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai' );
define( 'SKILITSA_AI_MODEL',    'gemini-2.5-flash' );
define( 'SKILITSA_AI_KEY',      'YOUR-GOOGLE-AI-STUDIO-KEY' );

// Mistral for the high-volume, low-judgement work.
define( 'SKILITSA_AI_BASE_URL_TRANSLATE', 'https://api.mistral.ai/v1' );
define( 'SKILITSA_AI_MODEL_TRANSLATE',    'mistral-small-latest' );
define( 'SKILITSA_AI_KEY_TRANSLATE',      'YOUR-MISTRAL-KEY' );

define( 'SKILITSA_AI_BASE_URL_ALT_TEXT', 'https://api.mistral.ai/v1' );
define( 'SKILITSA_AI_MODEL_ALT_TEXT',    'mistral-small-latest' );
define( 'SKILITSA_AI_KEY_ALT_TEXT',      'YOUR-MISTRAL-KEY' );
```

**Check the model names against each console before pasting.** Providers rename
and retire models faster than any document keeps up, and a wrong name shows up
as a failed call rather than a wrong answer — the run reports the failure and
writes nothing.

---

## How the settings resolve

Every setting is looked up in this order, and the first one found wins:

1. `SKILITSA_AI_<SETTING>_<TASK>` — that task only
2. `SKILITSA_AI_<SETTING>` — all tasks
3. the built-in default, which for endpoint and model is empty

`<SETTING>` is `KEY`, `MODEL`, `BASE_URL` or `PROVIDER`. `<TASK>` is the task
key in capitals: `MATRIX_QA`, `DRAFT_BREED_TRAITS`, `CHECK_PENALTIES`,
`TRANSLATE`, `ALT_TEXT`, `QUESTION_AUDIT`.

So one shared key covers everything, and any single task can be moved to
another provider — or switched off entirely by leaving its key blank — without
touching the rest.

## Providers that are not OpenAI-shaped

```php
define( 'SKILITSA_AI_PROVIDER', 'anthropic' );
```

Only two formats are implemented: `openai`, the default, which Gemini, Mistral,
Groq, OpenRouter, Together, LM Studio and Ollama all accept, and `anthropic`.

## Turning it off

Remove the key, or set it to an empty string. The screens keep working and say
the task is not configured.

## What it costs

Both providers have a free tier at time of writing, and every task is a batch
run someone starts by hand — nothing runs on a schedule, and nothing runs on a
page load. The most expensive task is matrix QA, which reads 25 breeds per call
and needs about ten calls to cover all 257.
