<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

final class Question
{
    public function __construct(
        public readonly string $key,
        public readonly float $weight = 1.0,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('Question key cannot be empty.');
        }
        if ($weight < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Question "%s": weight cannot be negative.', $key)
            );
        }
    }
}
