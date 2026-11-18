<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

use Skilitsa\Core\Ai\TaskDefinition;

interface AiClient
{
    /** Asks for one JSON object. Anything else is a failure, never a partial read. */
    public function ask(TaskDefinition $task, string $system, string $user): AiResponse;
}
