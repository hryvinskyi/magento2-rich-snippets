<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Head;

use Hryvinskyi\HeadTagManager\Api\HeadElement\HeadElementInterface;
use Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\AbstractSerializationStrategy;
use Hryvinskyi\RichSnippets\Model\Storefront\Head\Factory\MarkedScriptElementFactory;

/**
 * Keeps a marked script element in the block cache and brings it back as one
 *
 * It outranks the script element's own strategy, which a marked element would otherwise match as a script, so a
 * cached block restores the comment along with the script.
 */
class MarkedScriptSerializationStrategy extends AbstractSerializationStrategy
{
    private const PRIORITY = 200;

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return MarkedScriptElementFactory::ELEMENT_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return MarkedScriptElement::class;
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * @inheritDoc
     */
    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        return $element instanceof MarkedScriptElement ? ['content' => $element->getContent()] : [];
    }
}
