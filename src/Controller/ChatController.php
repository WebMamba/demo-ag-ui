<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chat\ChatMessage;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

/**
 * The first chat of the talk: a form, Turbo, and the agent. No AG-UI, no streaming:
 * the crew waits for the whole answer, and gets text only.
 */
final class ChatController extends AbstractController
{
    private const HISTORY = 'chat.history';

    #[Route('/chat', name: 'chat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('chat/index.html.twig', [
            'history' => $request->getSession()->get(self::HISTORY, []),
        ]);
    }

    #[Route('/chat', name: 'chat_send', methods: ['POST'])]
    public function send(
        #[MapRequestPayload] ChatMessage $input,
        Request $request,
        #[Target('basic')] AgentInterface $agent,
    ): Response {
        $session = $request->getSession();
        /** @var list<array{role: 'user'|'assistant', content: string}> $history */
        $history = $session->get(self::HISTORY, []);
        $history[] = ['role' => 'user', 'content' => $input->message];

        $messages = new MessageBag(...array_map(
            static fn (array $m) => 'user' === $m['role'] ? Message::ofUser($m['content']) : Message::ofAssistant($m['content']),
            $history,
        ));

        $answer = (string) $agent->call($messages)->getContent();

        $history[] = ['role' => 'assistant', 'content' => $answer];
        $session->set(self::HISTORY, $history);

        $exchange = ['question' => $input->message, 'answer' => $answer];

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('chat/send.stream.html.twig', $exchange);
        }

        return $this->redirectToRoute('chat_index');
    }

    #[Route('/chat/reset', name: 'chat_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        $request->getSession()->remove(self::HISTORY);

        return $this->redirectToRoute('chat_index');
    }
}
