<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Placement;

use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;

/**
 * The kinds of page and condition fields a placement may name: those of the page kind and condition field pools
 *
 * A module that adds a kind of page or a condition field to a pool makes it placeable with nothing else to change.
 */
class PoolPlacementVocabulary implements PlacementVocabularyInterface
{
    /**
     * @param PageKindPoolInterface $kinds
     * @param ConditionFieldPoolInterface $fields
     */
    public function __construct(
        private readonly PageKindPoolInterface $kinds,
        private readonly ConditionFieldPoolInterface $fields
    ) {
    }

    /**
     * @inheritDoc
     */
    public function hasPageKind(string $kind): bool
    {
        return $this->kinds->has($kind);
    }

    /**
     * @inheritDoc
     */
    public function pageKindOfField(string $field): ?string
    {
        return $this->fields->has($field) ? $this->fields->get($field)->getPageKind() : null;
    }
}
