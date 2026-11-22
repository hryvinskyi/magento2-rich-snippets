<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Position;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\Factory\MarkedScriptElementFactory;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionInterface;

/**
 * Writes the script blocks into the head, through the head tag manager
 *
 * The manager fills the head once the whole page has rendered, so a block standing at the end of the body still
 * reaches it; nothing is printed where the block stands. Each element is kept under a key of its snippet's code, so
 * two snippets that write the same JSON stay two elements. A marked block is written as the snippets' own script
 * element, which prints `<!-- rich-snippet: <code> -->` before its tag; an unmarked one as a plain script element.
 */
class HeadPosition implements OutputPositionInterface
{
    private const ELEMENT_TYPE = 'script';
    private const MARKED_ELEMENT_TYPE = MarkedScriptElementFactory::ELEMENT_TYPE;
    private const KEY_PREFIX = 'rich_snippets_';

    /**
     * @param HeadTagManagerInterface $head
     */
    public function __construct(private readonly HeadTagManagerInterface $head)
    {
    }

    /**
     * @inheritDoc
     */
    public function write(array $blocks, bool $marked): string
    {
        $type = $marked ? self::MARKED_ELEMENT_TYPE : self::ELEMENT_TYPE;
        foreach ($blocks as $block) {
            $this->head->createElement(
                $type,
                [
                    'attributes' => [
                        'type' => RenderedBlockInterface::MEDIA_TYPE,
                        RenderedBlockInterface::CODE_ATTRIBUTE => $block->getCode(),
                    ],
                    'content' => $block->getJson(),
                ],
                self::KEY_PREFIX . $block->getCode()
            );
        }

        return '';
    }
}
