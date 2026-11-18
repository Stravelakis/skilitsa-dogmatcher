<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

/**
 * The reviewed asymmetry defaults for all 24 traits.
 *
 * Distance in either direction does not cost the same (spec section 4.3):
 * `over` is charged when the breed exceeds what the user asked for, `under`
 * when it falls short. 0 means that direction is free, 1 is ordinary, 1.5 is
 * severe - reserved for welfare and safety, not preference.
 *
 * These are defaults, not settings. A trait the owner has tuned in the admin
 * editor carries `is_tuned = 1` and is never overwritten by an import; every
 * other trait is re-seeded from this table, so correcting a judgement here
 * corrects it everywhere on the next import.
 *
 * The note on each row is the one-line rationale the spec asks for. It is
 * shown beside the numbers in the matrix editor.
 */
final class TraitDefaults
{
    /** @var array<string,array{over: float, under: float, note: string}> */
    public const PENALTIES = [
        'energy_outdoor' => [
            'over' => 1.5, 'under' => 0.5,
            'note' => 'Too much energy for the owner is a welfare problem for the dog; too little only disappoints.',
        ],
        'energy_indoor' => [
            'over' => 1.0, 'under' => 0.5,
            'note' => 'A livelier dog than wanted is a daily friction; a calmer one rarely is.',
        ],
        'barking' => [
            'over' => 1.5, 'under' => 0.0,
            'note' => 'Barking in a Greek apartment block is a leading cause of rehoming. A quiet dog costs nothing at all: the question asks how much barking bothers you, and "it does not" must never read as "I want a barker".',
        ],
        'shedding' => [
            'over' => 1.0, 'under' => 0.0,
            'note' => 'Less hair than expected is never a loss.',
        ],
        'grooming' => [
            'over' => 1.0, 'under' => 0.0,
            'note' => 'Grooming costs time and money only in the upward direction.',
        ],
        'separation_tolerance' => [
            'over' => 0.25, 'under' => 1.5,
            'note' => 'A dog that cannot be left alone as long as the owner is away suffers, and the home pays for it.',
        ],
        'trainability' => [
            'over' => 0.25, 'under' => 1.0,
            'note' => 'A harder dog to train than expected costs patience and often money.',
        ],
        'handler_experience_needed' => [
            'over' => 1.5, 'under' => 0.0,
            'note' => 'A novice owner with a demanding dog is the classic failure. The reverse costs nothing.',
        ],
        'stranger_sociability' => [
            'over' => 0.25, 'under' => 0.75,
            'note' => 'A reserved dog in a house full of visitors needs managing; an over-friendly one rarely does.',
        ],
        'dog_sociability' => [
            'over' => 0.0, 'under' => 1.0,
            'note' => 'A dog-selective dog turns every walk into management.',
        ],
        'prey_drive_reactivity' => [
            'over' => 1.25, 'under' => 0.0,
            'note' => 'Safety: cats, bikes, children running. A calm dog carries no risk.',
        ],
        'leash_difficulty' => [
            'over' => 1.0, 'under' => 0.0,
            'note' => 'Pulling is a daily cost; walking nicely is not.',
        ],
        'clinginess' => [
            'over' => 0.75, 'under' => 0.5,
            'note' => 'Preference on both sides, not welfare. Kept mild in both directions.',
        ],
        'cuddliness' => [
            'over' => 0.5, 'under' => 0.75,
            'note' => 'Wanting affection and not getting it is the more keenly felt of the two.',
        ],
        'kid_friendly' => [
            'over' => 0.0, 'under' => 1.5,
            'note' => 'Child safety. Being better with children than required is never a cost.',
        ],
        'pet_compatible' => [
            'over' => 0.0, 'under' => 1.25,
            'note' => 'An existing pet in the home makes the shortfall concrete and immediate.',
        ],
        'drool_smell' => [
            'over' => 1.0, 'under' => 0.0,
            'note' => 'Only the upward direction is felt.',
        ],
        'destructiveness_puppy' => [
            'over' => 1.0, 'under' => 0.0,
            'note' => 'Only the upward direction is felt.',
        ],
        'travel_friendly' => [
            'over' => 0.0, 'under' => 1.0,
            'note' => 'Someone who travels and cannot take the dog has a real problem to solve.',
        ],
        'heat_tolerance' => [
            'over' => 0.0, 'under' => 1.5,
            'note' => 'Welfare, in a Greek summer. Tolerating heat better than needed costs nothing.',
        ],
        'alertness_guarding' => [
            'over' => 0.75, 'under' => 0.5,
            'note' => 'Alerting is barking; barking is charged again under its own trait, so this stays moderate.',
        ],
        'mental_stimulation_need' => [
            'over' => 1.25, 'under' => 0.0,
            'note' => 'An unmet need for stimulation comes back as destruction and stress.',
        ],
        'sensitivity' => [
            'over' => 1.0, 'under' => 0.25,
            'note' => 'A sensitive dog in a loud, busy home suffers. A resilient one does not.',
        ],
        'size' => [
            'over' => 1.0, 'under' => 0.5,
            'note' => 'Upward means space, transport, food and leash strength. Downward is mostly disappointment.',
        ],
    ];

    /** @return array{over: float, under: float} Symmetric for a trait we have no judgement on. */
    public static function forTrait(string $traitKey): array
    {
        $row = self::PENALTIES[$traitKey] ?? null;

        if ($row === null) {
            return ['over' => 1.0, 'under' => 1.0];
        }

        return ['over' => $row['over'], 'under' => $row['under']];
    }

    public static function noteFor(string $traitKey): string
    {
        return self::PENALTIES[$traitKey]['note'] ?? '';
    }
}
