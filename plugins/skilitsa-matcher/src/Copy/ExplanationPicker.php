<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Copy;

use Skilitsa\Core\Engine\Value\TraitVerdict;

/**
 * Chooses the sentence that explains one trait verdict.
 *
 * The copy already exists and has never been displayed: 23 of 24 traits have
 * `pos_<trait>_1`, `pos_<trait>_2`, `neg_<trait>_1` and `neg_<trait>_2` in
 * RESULT_TEMPLATES, and every trait carries directional `warning_too_low` /
 * `warning_too_high` prose.
 *
 * Variant choice is deterministic, derived from the breed key, so two breeds on
 * one page do not open with the same sentence but a shared result URL always
 * renders identically.
 */
final class ExplanationPicker
{
    /**
     * @param array<string,string>               $templates RESULT_TEMPLATES rows
     * @param array<string,array<string,string>> $traitCopy trait_key => row with warning_too_low/high
     */
    public function pick(
        TraitVerdict $verdict,
        array $templates,
        array $traitCopy,
        string $variantSeed = '',
    ): string {
        if ($verdict->tier === TraitVerdict::NEUTRAL) {
            // Neutral is deliberately silent (spec section 4.9). Saying
            // "this is fine" about eleven traits buries the two that matter.
            return '';
        }

        if ($verdict->tier === TraitVerdict::OPPOSED) {
            $key = $verdict->templateKey();

            return trim((string) ($traitCopy[$verdict->traitKey][$key] ?? ''));
        }

        $base = $verdict->templateKey();
        if ($base === '') {
            return '';
        }

        $variants = [];
        foreach ([1, 2] as $n) {
            $body = trim((string) ($templates[$base . '_' . $n] ?? ''));
            if ($body !== '') {
                $variants[] = $body;
            }
        }

        if ($variants === []) {
            // Some traits have the unsuffixed key only; `size` has neither.
            return trim((string) ($templates[$base] ?? ''));
        }

        $index = crc32($variantSeed . $verdict->traitKey) % count($variants);

        return $variants[$index];
    }
}
