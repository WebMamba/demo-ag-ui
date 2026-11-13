<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Tests\Support\NavigatorToolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RenderLinkListTest extends KernelTestCase
{
    use NavigatorToolbox;

    /**
     * The model sends JSON, not PHP objects: going through the toolbox proves the
     * generated schema and the component agree on the item shape.
     */
    public function testTheToolboxRendersALinkListFromJsonArguments(): void
    {
        self::bootKernel();

        $result = self::navigatorToolbox()->execute(new ToolCall(
            'call_1',
            'render_link_list',
            [
                'title' => 'Top restaurants in Lyon',
                'items' => [
                    ['title' => 'Le Bouchon des Filles', 'url' => 'https://example.com/bouchon', 'description' => 'Classic bouchon.'],
                    ['title' => 'Daniel & Denise', 'url' => 'https://example.com/daniel'],
                ],
            ],
        ));

        $rendering = $result->getResult();
        $html = $rendering->content['html'];

        // The two channels: markup for the browser, a sentence for the model. If the
        // model-facing string ever becomes the markup again, it is back to re-reading
        // its own HTML on every later turn.
        self::assertStringNotContainsString('<', (string) $rendering);
        self::assertStringContainsString('agent-component-links', $html);
        self::assertStringContainsString('Le Bouchon des Filles', $html);
        self::assertStringContainsString('https://example.com/daniel', $html);
        self::assertStringContainsString('Classic bouchon.', $html);
    }

    public function testItRewritesUnsafeUrls(): void
    {
        self::bootKernel();

        $result = self::navigatorToolbox()->execute(new ToolCall(
            'call_2',
            'render_link_list',
            [
                'title' => 'Sketchy',
                'items' => [['title' => 'Do not click', 'url' => 'javascript:alert(1)']],
            ],
        ));

        self::assertStringNotContainsString('javascript:', $result->getResult()->content['html']);
    }
}
