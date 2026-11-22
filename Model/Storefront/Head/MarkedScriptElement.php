<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Head;

use Hryvinskyi\HeadTagManager\Model\HeadElement\ScriptElement;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * A snippet's script element in the head, preceded by `<!-- rich-snippet: <code> -->`
 *
 * The code is read from the element's own RenderedBlockInterface::CODE_ATTRIBUTE attribute, so it survives the block
 * cache with the attributes and needs no data of its own. Only a code in the snippet code alphabet is written into
 * the comment, and that alphabet has no character that can end one; any other value writes the element without it.
 * The script itself renders as the head tag manager's script element does.
 */
class MarkedScriptElement extends ScriptElement
{
    private const COMMENT = '<!-- rich-snippet: %s -->';

    /**
     * @inheritDoc
     */
    public function render(): string
    {
        $code = $this->getAttribute(RenderedBlockInterface::CODE_ATTRIBUTE);
        $script = parent::render();
        if (!is_string($code) || preg_match(SnippetInterface::CODE_PATTERN, $code) !== 1) {
            return $script;
        }

        return sprintf(self::COMMENT, $code) . "\n" . $script;
    }
}
