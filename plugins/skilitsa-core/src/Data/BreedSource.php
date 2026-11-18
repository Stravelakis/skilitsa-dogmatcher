<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

use Skilitsa\Core\Engine\Value\Breed;

interface BreedSource
{
    /** @return list<Breed> */
    public function breeds(): array;
}
