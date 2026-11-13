<?php

declare(strict_types=1);

namespace App\Controller;

use AgUi\Bundle\Http\AgUiResponse;
use AgUi\Run\RunAgentInput;
use AgUi\Bridge\SymfonyAi\AgUi;
use AgUi\Bridge\SymfonyAi\Exception\InvalidStateException;
use App\Ai\Passage\PassagePlan;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The AG-UI endpoint: a RunAgentInput in, the navigator's event stream out, with the
 * passage plan as the shared state. The route picks the agent — its platform and model
 * are in ai.yaml; what the interface looks like reaches it through #[AsAgentContext]
 * (Theme, Locale).
 */
final readonly class StreamController
{
    #[Route('/agent/stream', name: 'agent_stream', methods: ['POST'])]
    public function __invoke(RunAgentInput $input, AgentInterface $navigator, AgUi $agUi, PassagePlan $plan): AgUiResponse
    {
        try {
            return new AgUiResponse($agUi->run($input, $navigator, state: $plan, options: ['max_tokens' => 8192, 'thinking' => ['type' => 'adaptive']]));
        } catch (InvalidStateException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
