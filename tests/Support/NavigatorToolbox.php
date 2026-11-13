<?php

declare(strict_types=1);

namespace App\Tests\Support;

use AgUi\Run\RunAgentInput;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;

/**
 * The navigator's toolbox as the model sees it during an AG-UI run: its own tools, plus
 * the agent components, which are only offered while AgUi runs the navigator.
 */
trait NavigatorToolbox
{
    private static function navigatorToolbox(): ToolboxInterface
    {
        self::getContainer()->get('ag_ui.run_scope')->open(
            RunAgentInput::fromArray(['threadId' => 'thread_1', 'runId' => 'run_1', 'messages' => []]),
            [],
            agent: 'navigator',
        );

        return self::getContainer()->get('ai.toolbox.navigator');
    }
}
