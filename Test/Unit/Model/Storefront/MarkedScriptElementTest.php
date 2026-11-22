<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront;

use Hryvinskyi\Base\Api\Html\AttributeRendererInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\MarkedScriptElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

/**
 * The snippets' head script element prints the comment naming its code before the script, and only a code in the
 * code alphabet ever reaches the comment
 */
class MarkedScriptElementTest extends TestCase
{
    public function testTheCommentNamingTheCodeComesBeforeTheScript(): void
    {
        $element = $this->element();
        $element->setAttributes(['type' => 'application/ld+json', 'data-snippet' => 'organization']);
        $element->setContent('{"b":2}');

        self::assertSame(
            "<!-- rich-snippet: organization -->\n"
            . '<script type="application/ld+json" data-snippet="organization">{"b":2}</script>',
            $element->render()
        );
    }

    public function testACodeOutsideTheCodeAlphabetWritesTheScriptWithoutAComment(): void
    {
        $element = $this->element();
        $element->setAttributes(['type' => 'application/ld+json', 'data-snippet' => 'x--><script>alert(1)</script>']);
        $element->setContent('{}');

        $html = $element->render();

        self::assertStringNotContainsString('<!--', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    /**
     * An element whose secure renderer writes the tag with its attributes escaped, as the platform's does
     *
     * @return MarkedScriptElement
     */
    private function element(): MarkedScriptElement
    {
        $renderer = $this->createStub(SecureHtmlRenderer::class);
        $renderer->method('renderTag')->willReturnCallback(
            static function (string $tag, array $attributes, ?string $content): string {
                $written = '';
                foreach ($attributes as $name => $value) {
                    $text = is_scalar($value) ? (string) $value : '';
                    $written .= ' ' . $name . '="' . htmlspecialchars($text, ENT_QUOTES) . '"';
                }

                return '<' . $tag . $written . '>' . $content . '</' . $tag . '>';
            }
        );

        return new MarkedScriptElement($this->createStub(AttributeRendererInterface::class), $renderer);
    }
}
