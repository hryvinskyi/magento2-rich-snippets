<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Head\Factory;

use Hryvinskyi\HeadTagManager\Model\HeadElement\Factory\AbstractHeadElementFactory;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\MarkedScriptElement;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\MarkedScriptElementFactory as MarkedScriptElementObjectFactory;

/**
 * Creates the snippets' marked script elements for the head tag manager, under the element type
 * `rich_snippet_script`
 */
class MarkedScriptElementFactory extends AbstractHeadElementFactory
{
    public const ELEMENT_TYPE = 'rich_snippet_script';
    private const SORT_ORDER = 100;

    /**
     * @param MarkedScriptElementObjectFactory $elementFactory creates the element with its services injected
     */
    public function __construct(private readonly MarkedScriptElementObjectFactory $elementFactory)
    {
    }

    /**
     * @inheritDoc
     */
    protected function createElement(array $data): MarkedScriptElement
    {
        $element = $this->elementFactory->create();
        $content = $this->getContent($data);
        if ($content !== null) {
            $element->setContent($content);
        }

        return $element;
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultSortOrder(): int
    {
        return self::SORT_ORDER;
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return self::ELEMENT_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return MarkedScriptElement::class;
    }
}
