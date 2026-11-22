<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Position;

use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

/**
 * Prints the script blocks where the storefront block stands, at the end of the body
 *
 * The elements go through the platform's secure renderer, which escapes the attributes and lets the content security
 * policy allow the inline script. A marked block is preceded by `<!-- rich-snippet: <code> -->`. Only a code in the
 * snippet code alphabet is written into a comment, and that alphabet has no character that can end one; the
 * snippet's name, which is free text, is never written.
 */
class BodyEndPosition implements OutputPositionInterface
{
    private const COMMENT = '<!-- rich-snippet: %s -->';

    /**
     * @param SecureHtmlRenderer $renderer
     */
    public function __construct(private readonly SecureHtmlRenderer $renderer)
    {
    }

    /**
     * @inheritDoc
     */
    public function write(array $blocks, bool $marked): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $code = $block->getCode();
            if ($marked && preg_match(SnippetInterface::CODE_PATTERN, $code) === 1) {
                $html .= sprintf(self::COMMENT, $code) . "\n";
            }
            $html .= $this->renderer->renderTag(
                'script',
                [
                    'type' => RenderedBlockInterface::MEDIA_TYPE,
                    RenderedBlockInterface::CODE_ATTRIBUTE => $code,
                ],
                $block->getJson(),
                false
            ) . "\n";
        }

        return $html;
    }
}
