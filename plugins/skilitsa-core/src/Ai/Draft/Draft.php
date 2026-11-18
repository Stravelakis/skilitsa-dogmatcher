<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

use DomainException;

/**
 * One suggestion from an AI task, and everything needed to judge it later.
 *
 * Nothing an AI task produces is ever written. It produces one of these, which
 * is inert: a row saying what it would change, from what, by which model, when.
 * Only a person moves it out of `pending`, and approving it goes back through
 * the same admin form a person would have typed into - so a model can never
 * write a value a human could not have.
 *
 * Immutable. `approve()` and `reject()` return a new draft rather than mutating,
 * so a half-applied review cannot exist.
 */
final class Draft
{
    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /** One breed's value for one trait, 1-5. */
    public const TARGET_BREED_TRAIT = 'breed_trait';

    /** penalty_over / penalty_under / global_weight on a trait definition. */
    public const TARGET_TRAIT_PENALTY = 'trait_penalty';

    /** Copy: a template body, a warning line, alt text. */
    public const TARGET_COPY = 'copy';

    private const TARGET_TYPES = [
        self::TARGET_BREED_TRAIT,
        self::TARGET_TRAIT_PENALTY,
        self::TARGET_COPY,
    ];

    private function __construct(
        public readonly string $task,
        public readonly string $targetType,
        public readonly string $targetKey,
        public readonly string $field,
        public readonly string $currentValue,
        public readonly string $suggestedValue,
        public readonly string $model,
        public readonly string $note,
        public readonly string $createdAt,
        public readonly string $status,
        public readonly ?string $reviewedAt,
        public readonly bool $isStale,
        public readonly ?int $id,
    ) {
    }

    public static function create(
        string $task,
        string $targetType,
        string $targetKey,
        string $field,
        string $currentValue,
        string $suggestedValue,
        string $model,
        string $note = '',
        string $createdAt = '',
        ?int $id = null,
    ): self {
        if (!in_array($targetType, self::TARGET_TYPES, true)) {
            throw new DomainException(sprintf('Unknown draft target type "%s".', $targetType));
        }

        // A suggestion that changes nothing is not a finding, and the
        // reviewer's attention is the scarcest thing in this whole system.
        if (self::sameValue($currentValue, $suggestedValue)) {
            throw new DomainException('A draft must differ from the value it replaces.');
        }

        return new self(
            task: $task,
            targetType: $targetType,
            targetKey: $targetKey,
            field: $field,
            currentValue: $currentValue,
            suggestedValue: $suggestedValue,
            model: $model,
            note: $note,
            createdAt: $createdAt !== '' ? $createdAt : gmdate('Y-m-d H:i:s'),
            status: self::PENDING,
            reviewedAt: null,
            isStale: false,
            id: $id,
        );
    }

    /** Rebuilds a draft from storage without re-running the creation guards. */
    public static function fromStorage(array $row): self
    {
        return new self(
            task: (string) ($row['task'] ?? ''),
            targetType: (string) ($row['target_type'] ?? ''),
            targetKey: (string) ($row['target_key'] ?? ''),
            field: (string) ($row['field'] ?? ''),
            currentValue: (string) ($row['current_value'] ?? ''),
            suggestedValue: (string) ($row['suggested_value'] ?? ''),
            model: (string) ($row['model'] ?? ''),
            note: (string) ($row['note'] ?? ''),
            createdAt: (string) ($row['created_at'] ?? ''),
            status: (string) ($row['status'] ?? self::PENDING),
            reviewedAt: isset($row['reviewed_at']) && $row['reviewed_at'] !== null
                ? (string) $row['reviewed_at']
                : null,
            isStale: false,
            id: isset($row['id']) ? (int) $row['id'] : null,
        );
    }

    public function approve(string $at = ''): self
    {
        $this->refuseIfJudged();

        if ($this->isStale) {
            throw new DomainException(
                'This draft is stale: the value changed after it was written. Reject it and run the task again.'
            );
        }

        return $this->judged(self::APPROVED, $at);
    }

    public function reject(string $at = ''): self
    {
        // Always available, even when stale: clearing a draft out is the safe
        // direction and must never be blocked.
        $this->refuseIfJudged();

        return $this->judged(self::REJECTED, $at);
    }

    /** @param string $liveValue What the field holds right now. */
    public function isStaleAgainst(string $liveValue): bool
    {
        return !self::sameValue($this->currentValue, $liveValue);
    }

    public function markStale(): self
    {
        return new self(
            $this->task, $this->targetType, $this->targetKey, $this->field,
            $this->currentValue, $this->suggestedValue, $this->model, $this->note,
            $this->createdAt, $this->status, $this->reviewedAt, true, $this->id,
        );
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * Identity of the suggestion itself, so a weekly run does not build a pile
     * of identical rows. Deliberately excludes the model and the timestamp: the
     * same suggestion from a newer model is the same suggestion.
     */
    public function fingerprint(): string
    {
        return md5(implode('|', [
            $this->task,
            $this->targetType,
            $this->targetKey,
            $this->field,
            $this->suggestedValue,
        ]));
    }

    private function judged(string $status, string $at): self
    {
        return new self(
            $this->task, $this->targetType, $this->targetKey, $this->field,
            $this->currentValue, $this->suggestedValue, $this->model, $this->note,
            $this->createdAt, $status,
            $at !== '' ? $at : gmdate('Y-m-d H:i:s'),
            $this->isStale, $this->id,
        );
    }

    private function refuseIfJudged(): void
    {
        if (!$this->isPending()) {
            throw new DomainException(sprintf('This draft was already %s.', $this->status));
        }
    }

    /** 2 and 2.00 are the same value; the database returns the second. */
    private static function sameValue(string $a, string $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.0001;
        }

        return trim($a) === trim($b);
    }
}
