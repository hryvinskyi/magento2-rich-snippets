<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The snippet repository as the module's own use cases hold it: what it reads can be changed through the snippet's
 * methods and saved back
 */
interface SnippetRootRepositoryInterface extends SnippetRepositoryInterface
{
    /**
     * @inheritDoc
     *
     * @return SnippetRootInterface
     * @throws NoSuchEntityException
     */
    public function get(int $id): SnippetRootInterface;

    /**
     * @inheritDoc
     *
     * @return SnippetRootInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): SnippetRootInterface;

    /**
     * @inheritDoc
     *
     * @return SnippetRootInterface
     */
    public function save(SnippetInterface $snippet): SnippetRootInterface;

    /**
     * @inheritDoc
     *
     * @return list<SnippetRootInterface>
     */
    public function list(
        ?int $storeId = null,
        ?string $pageKind = null,
        ?bool $active = null,
        ?bool $block = null
    ): array;
}
