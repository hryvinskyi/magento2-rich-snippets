<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterfaceFactory;

/**
 * Delegates to the generated factory of the snippet aggregate contract
 */
class SnippetFactoryAdapter implements SnippetFactoryInterface
{
    /**
     * @param SnippetRootInterfaceFactory $generated
     * @param ConditionTreeFactoryInterface $conditions
     */
    public function __construct(
        private readonly SnippetRootInterfaceFactory $generated,
        private readonly ConditionTreeFactoryInterface $conditions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function draft(string $code, string $name, bool $isBlock): SnippetRootInterface
    {
        return $this->restore(
            null,
            $code,
            $name,
            '',
            '',
            $isBlock,
            false,
            [],
            $this->conditions->tree(),
            [SnippetInterface::ALL_STORES],
            null,
            0,
            null,
            ''
        );
    }

    /**
     * @inheritDoc
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
    ): SnippetRootInterface {
        return $this->generated->create([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'template' => $template,
            'type' => $type,
            'isBlock' => $isBlock,
            'active' => $active,
            'pageKinds' => $pageKinds,
            'conditions' => $conditions,
            'storeIds' => $storeIds,
            'origin' => $origin,
            'sortOrder' => $sortOrder,
            'updatedAt' => $updatedAt,
            'updatedBy' => $updatedBy,
        ]);
    }
}
