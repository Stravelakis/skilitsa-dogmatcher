<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

use DomainException;
use Skilitsa\Core\Admin\Form\BreedValuesForm;
use Skilitsa\Core\Admin\Form\TraitMatrixForm;

/**
 * Turns an approved draft into the input a human form would have received.
 *
 * This class is the wall the whole design rests on. Approval never writes and
 * never validates anything itself: it builds the same `$_POST` shape the admin
 * screen builds, hands it to the same form, and passes on only what that form
 * accepted. A model therefore cannot write a value a person could not have
 * typed, and every range check written for a human applies unchanged.
 *
 * Pure. The caller supplies the surrounding context; nothing here reads a
 * database or a clock it was not given.
 */
final class DraftReview
{
    /**
     * @param array{traitKeys?: list<string>, row?: array{over: float, under: float, weight: float}} $context
     */
    public static function approve(Draft $draft, array $context): ReviewOutcome
    {
        try {
            $approved = $draft->approve();
        } catch (DomainException $e) {
            return ReviewOutcome::refused($draft, $e->getMessage());
        }

        return match ($draft->targetType) {
            Draft::TARGET_BREED_TRAIT   => self::breedTrait($draft, $approved, $context['traitKeys'] ?? []),
            Draft::TARGET_TRAIT_PENALTY => self::traitPenalty($draft, $approved, $context['row'] ?? null),
            Draft::TARGET_COPY          => self::copy($draft, $approved),
            default                     => ReviewOutcome::refused(
                $draft,
                sprintf('Drafts of type "%s" cannot be applied yet.', $draft->targetType)
            ),
        };
    }

    public static function reject(Draft $draft): ReviewOutcome
    {
        try {
            return ReviewOutcome::recorded($draft->reject());
        } catch (DomainException $e) {
            return ReviewOutcome::refused($draft, $e->getMessage());
        }
    }

    /**
     * Copy is checked against the same whitelist the task used to propose it.
     *
     * There is no admin form for a template body, so this is the one target
     * type whose rules live here rather than being borrowed from a screen:
     * the address must parse, the field must be one the target allows, and
     * the text must not be empty. A blank approved suggestion would erase a
     * line the site depends on and look like a successful save.
     */
    private static function copy(Draft $draft, Draft $approved): ReviewOutcome
    {
        $target = CopyTarget::parse($draft->targetKey);

        if ($target === null) {
            return ReviewOutcome::refused($draft, sprintf('"%s" is not a copy target.', $draft->targetKey));
        }

        if (!$target->allows($draft->field)) {
            return ReviewOutcome::refused(
                $draft,
                sprintf('"%s" is not a field that may be written on %s.', $draft->field, $target->kind)
            );
        }

        if (trim($draft->suggestedValue) === '') {
            return ReviewOutcome::refused($draft, 'An empty rewrite would erase the line.');
        }

        return ReviewOutcome::applied($approved, [
            'target' => $target->address(),
            'field'  => $draft->field,
            'text'   => $draft->suggestedValue,
        ]);
    }

    /** @param list<string> $traitKeys */
    private static function breedTrait(Draft $draft, Draft $approved, array $traitKeys): ReviewOutcome
    {
        $submission = BreedValuesForm::parse(
            ['traits' => [$draft->field => $draft->suggestedValue]],
            $traitKeys
        );

        if (!$submission->isValid()) {
            return ReviewOutcome::refused(
                $draft,
                $submission->errorFor($draft->field) ?? 'The value was refused.'
            );
        }

        // An unknown trait is silently dropped by the form rather than
        // rejected, so an empty result means the field is not one of ours.
        if ($submission->values === []) {
            return ReviewOutcome::refused($draft, sprintf('"%s" is not a trait in this matrix.', $draft->field));
        }

        return ReviewOutcome::applied($approved, $submission->values);
    }

    /** @param array{over: float, under: float, weight: float}|null $row */
    private static function traitPenalty(Draft $draft, Draft $approved, ?array $row): ReviewOutcome
    {
        if ($row === null) {
            return ReviewOutcome::refused($draft, 'The current values for this trait are unknown.');
        }

        $field = match ($draft->field) {
            'penalty_over'  => 'over',
            'penalty_under' => 'under',
            'global_weight' => 'weight',
            default         => null,
        };

        if ($field === null) {
            return ReviewOutcome::refused($draft, sprintf('"%s" is not a tunable field.', $draft->field));
        }

        // The rest of the row travels unchanged: approving one number must not
        // quietly reset the two beside it.
        $post = ['traits' => [$draft->targetKey => [
            'over'   => (string) $row['over'],
            'under'  => (string) $row['under'],
            'weight' => (string) $row['weight'],
        ]]];

        $post['traits'][$draft->targetKey][$field] = $draft->suggestedValue;

        $submission = TraitMatrixForm::parse($post);

        if (!$submission->isValid()) {
            return ReviewOutcome::refused(
                $draft,
                $submission->errorFor($draft->targetKey, $field) ?? 'The value was refused.'
            );
        }

        $values = $submission->rows;

        // A human approved it, so it is a human decision: the next import must
        // not re-seed this row from the code defaults.
        $values[$draft->targetKey]['is_tuned'] = true;

        return ReviewOutcome::applied($approved, $values);
    }
}
