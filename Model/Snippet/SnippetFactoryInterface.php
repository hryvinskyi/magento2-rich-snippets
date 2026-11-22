<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Creates snippets: a new draft, or one restored from what a store or a caller holds
 */
interface SnippetFactoryInterface
{
    /**
     * A new snippet: no template yet, switched off, output nowhere, every store view
     *
     * @param string $code a code from the code allocator
     * @param string $name
     * @param bool $isBlock
     * @return SnippetRootInterface
     * @throws SnippetRuleException when the code or the name breaks a snippet rule
     */
    public function draft(string $code, string $name, bool $isBlock): SnippetRootInterface;

    /**
     * A snippet with every value given, checked against the snippet rules
     *
     * @param int|null $id
     * @param string $code
     * @param string $name
     * @param string $template
     * @param string $type
     * @param bool $isBlock
     * @param bool $active
     * @param list<string> $pageKinds
     * @param ConditionTreeInterface $conditions
     * @param list<int> $storeIds
     * @param OriginInterface|null $origin
     * @param int $sortOrder
     * @param string|null $updatedAt
     * @param string $updatedBy
     * @return SnippetRootInterface
     * @throws SnippetRuleException when a value breaks a snippet rule
     */
    public function restore(
        ?int $id,
        string $code,
        string $name,
        string $template,
        string $type,
        bool $isBlock,
        bool $active,
        array $pageKinds,
        ConditionTreeInterface $conditions,
        array $storeIds,
        ?OriginInterface $origin,
        int $sortOrder,
        ?string $updatedAt,
        string $updatedBy
    ): SnippetRootInterface;
}
