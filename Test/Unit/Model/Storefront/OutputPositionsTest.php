<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Hryvinskyi\RichSnippets\Model\Storefront\Position\BodyEndPosition;
use Hryvinskyi\RichSnippets\Model\Storefront\Position\HeadPosition;
use Hryvinskyi\RichSnippets\Model\Storefront\RenderedBlock;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The head gets one element per snippet under a key of its code, the snippets' own marked element when asked; the
 * end of the body prints each script element, marked by its code when asked; a position nobody writes fails loudly
 */
class OutputPositionsTest extends TestCase
{
    public function testTheHeadKeepsOneElementPerSnippetEvenWhenTwoWriteTheSameJson(): void
    {
        $created = [];
        $head = $this->recordingHead($created);

        $printed = (new HeadPosition($head))->write(
            [new RenderedBlock('product', 'Product', '{"a":1}'), new RenderedBlock('product-2', 'Product', '{"a":1}')],
            false
        );

        self::assertSame('', $printed);
        self::assertSame(
            [
                ['script', [
                    'attributes' => ['type' => 'application/ld+json', 'data-snippet' => 'product'],
                    'content' => '{"a":1}',
                ], 'rich_snippets_product'],
                ['script', [
                    'attributes' => ['type' => 'application/ld+json', 'data-snippet' => 'product-2'],
                    'content' => '{"a":1}',
                ], 'rich_snippets_product-2'],
            ],
            $created
        );
    }

    public function testAMarkedBlockGoesIntoTheHeadAsTheSnippetsOwnScriptElement(): void
    {
        $created = [];
        $head = $this->recordingHead($created);

        (new HeadPosition($head))->write([new RenderedBlock('organization', 'Organization', '{"b":2}')], true);

        self::assertSame(
            [
                ['rich_snippet_script', [
                    'attributes' => ['type' => 'application/ld+json', 'data-snippet' => 'organization'],
                    'content' => '{"b":2}',
                ], 'rich_snippets_organization'],
            ],
            $created
        );
    }

    public function testTheEndOfTheBodyPrintsEachScriptMarkedByItsCodeWhenAsked(): void
    {
        $position = new BodyEndPosition($this->renderer());
        $blocks = [new RenderedBlock('organization', 'Organization', '{"b":2}')];

        self::assertSame(
            "<!-- rich-snippet: organization -->\n"
            . '<script type="application/ld+json" data-snippet="organization">{"b":2}</script>' . "\n",
            $position->write($blocks, true)
        );
        self::assertSame(
            '<script type="application/ld+json" data-snippet="organization">{"b":2}</script>' . "\n",
            $position->write($blocks, false)
        );
    }

    public function testACodeOutsideTheCodeAlphabetIsNeverWrittenIntoAComment(): void
    {
        $printed = (new BodyEndPosition($this->renderer()))->write(
            [new RenderedBlock('x--><script>alert(1)</script>', 'Thing', '{}')],
            true
        );

        self::assertStringNotContainsString('<!--', $printed);
    }

    public function testAPositionNobodyWritesFailsLoudly(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new OutputPositionPool([]))->get('body_end');
    }

    public function testThePoolOffersEachPlaceInItsOrderWithItsWords(): void
    {
        $writer = new BodyEndPosition($this->renderer());
        $pool = new OutputPositionPool([
            'body_end' => [
                'writer' => $writer,
                'label' => 'At the end',
                'place' => 'at the end',
                'line' => 'Ends.',
                'note' => 'End',
            ],
            'head' => [
                'writer' => $writer,
                'label' => 'In the head',
                'place' => 'in the head',
                'line' => 'Heads.',
                'note' => 'Head',
            ],
        ]);

        self::assertSame(['body_end', 'head'], $pool->codes());
        self::assertSame($writer, $pool->get('head'));
        self::assertSame(['In the head', 'in the head', 'Heads.', 'Head'], [
            $pool->label('head'),
            $pool->place('head'),
            $pool->line('head'),
            $pool->note('head'),
        ]);
        self::assertTrue($pool->has('body_end'));
        self::assertFalse($pool->has('footer'));
    }

    /**
     * @param array<array-key, mixed> $positions
     */
    #[DataProvider('malformedPositions')]
    public function testAMalformedPlaceIsRefusedWhenThePoolIsBuilt(array $positions): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OutputPositionPool($positions);
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function malformedPositions(): array
    {
        $writer = new class implements OutputPositionInterface {
            /**
             * @inheritDoc
             */
            public function write(array $blocks, bool $marked): string
            {
                return '';
            }
        };
        $place = [
            'writer' => $writer,
            'label' => 'In the head',
            'place' => 'in the head',
            'line' => 'Heads.',
            'note' => 'Head',
        ];

        return [
            'a code that is not a setting value' => [['In Head' => $place]],
            'no writer' => [['head' => ['writer' => null] + $place]],
            'no label' => [['head' => ['label' => ''] + $place]],
            'no place' => [['head' => array_diff_key($place, ['place' => true])]],
            'no line' => [['head' => ['line' => 7] + $place]],
            'no note' => [['head' => array_diff_key($place, ['note' => true])]],
        ];
    }

    /**
     * A head tag manager that records each element asked of it
     *
     * @param list<array{string, array<mixed>, string|null}> $created receives each element asked for
     * @return HeadTagManagerInterface
     */
    private function recordingHead(array &$created): HeadTagManagerInterface
    {
        $head = $this->createMock(HeadTagManagerInterface::class);
        $head->method('createElement')->willReturnCallback(
            function (string $type, array $data, ?string $key) use (&$created): HeadElementInterface {
                $created[] = [$type, $data, $key];

                return $this->createMock(HeadElementInterface::class);
            }
        );

        return $head;
    }

    /**
     * A secure renderer that writes the tag with its attributes escaped, as the platform's does
     *
     * @return SecureHtmlRenderer
     */
    private function renderer(): SecureHtmlRenderer
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->method('renderTag')->willReturnCallback(
            static function (string $tag, array $attributes, ?string $content): string {
                $written = '';
                foreach ($attributes as $name => $value) {
                    self::assertIsString($value);
                    $written .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
                }

                return '<' . $tag . $written . '>' . $content . '</' . $tag . '>';
            }
        );

        return $renderer;
    }
}
