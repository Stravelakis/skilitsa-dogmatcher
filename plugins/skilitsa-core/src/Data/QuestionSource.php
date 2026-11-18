<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;

interface QuestionSource
{
    /** @return list<Question> */
    public function questions(): array;

    /** @return list<QuestionTraitLink> */
    public function links(): array;
}
