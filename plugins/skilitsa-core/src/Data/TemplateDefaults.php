<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

/**
 * Explanation copy that the sheet does not carry.
 *
 * RESULT_TEMPLATES covers 23 of 24 traits with `pos_<trait>_1..2` and
 * `neg_<trait>_1..2`. `size` has neither, so a size verdict - aligned or
 * pitfall - renders as an empty string and the trait silently disappears from
 * the result page.
 *
 * The sheet always wins: a key defined there overrides the same key here, so
 * the owner can take this copy over without a code change.
 *
 * Voice matches the existing rows: first person, the reader talking about
 * themselves, plain Greek, an emoji tail. No direction is implied - the sharp
 * directional cases use the trait's own warning_too_low / warning_too_high.
 */
final class TemplateDefaults
{
    /** @var array<string,string> */
    public const TEMPLATES = [
        'pos_size_1' => 'Μου ταιριάζει στο μέγεθος: χωράει άνετα στον χώρο μου και τη διαχειρίζομαι εύκολα στη βόλτα, στο αυτοκίνητο, στον κτηνίατρο 📏🐶',
        'pos_size_2' => 'Το μέγεθός της είναι ακριβώς αυτό που φανταζόμουν — ούτε «πολλή σκυλίτσα» για το σπίτι μου, ούτε λιγότερη απ’ όσο ήθελα 🏠❤️',
        'neg_size_1' => 'Το μέγεθος δεν είναι ακριβώς αυτό που είχα στο μυαλό μου. Αξίζει να το σκεφτώ πρακτικά: χώρος, μεταφορά, τροφή, δύναμη στο λουρί 📏🤔',
        'neg_size_2' => 'Στο μέγεθος έχουμε απόκλιση. Δεν είναι αποκλειστικό «όχι», αλλά καλό είναι να τη δω από κοντά πριν αποφασίσω 🐕📌',
    ];

    /**
     * @param array<string,string> $fromSheet
     * @return array<string,string> The sheet's rows, plus any default it lacks.
     */
    public static function fill(array $fromSheet): array
    {
        foreach (self::TEMPLATES as $key => $body) {
            if (trim((string) ($fromSheet[$key] ?? '')) === '') {
                $fromSheet[$key] = $body;
            }
        }

        return $fromSheet;
    }
}
