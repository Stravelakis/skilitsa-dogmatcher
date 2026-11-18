<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

use Skilitsa\Core\Engine\Value\TraitDefinition;

interface TraitSource
{
    /** @return array<string,TraitDefinition> keyed by trait key */
    public function traits(): array;
}
