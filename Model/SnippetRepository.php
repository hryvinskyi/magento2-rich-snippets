<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\DeleteSnippet;
use Hryvinskyi\RichSnippets\Model\Snippet\SaveSnippet;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetReaderInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Reads stored snippets, and saves and deletes them through the save and delete use cases
 */
class SnippetRepository implements SnippetRootRepositoryInterface
{
    /**
     * @param SnippetReaderInterface $reader
     * @param SaveSnippet $saveSnippet
     * @param DeleteSnippet $deleteSnippet
     */
    public function __construct(
        private readonly SnippetReaderInterface $reader,
        private readonly SaveSnippet $saveSnippet,
        private readonly DeleteSnippet $deleteSnippet
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $id): SnippetRootInterface
    {
        $snippet = $this->reader->byId($id);
        if ($snippet === null) {
            throw new NoSuchEntityException(__('The snippet with ID "%1" does not exist.', $id));
        }

        return $snippet;
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): SnippetRootInterface
    {
        $snippet = $this->reader->byCode($code);
        if ($snippet === null) {
            throw new NoSuchEntityException(__('The snippet with code "%1" does not exist.', $code));
        }

        return $snippet;
    }

    /**
     * @inheritDoc
     */
    public function list(
        ?int $storeId = null,
        ?string $pageKind = null,
        ?bool $active = null,
        ?bool $block = null
    ): array {
        return $this->reader->matching($storeId, $pageKind, $active, $block);
    }

    /**
     * @inheritDoc
     */
    public function save(SnippetInterface $snippet): SnippetRootInterface
    {
        return $this->saveSnippet->save($snippet);
    }

    /**
     * @inheritDoc
     */
    public function delete(SnippetInterface $snippet): void
    {
        $this->deleteSnippet->delete($snippet);
    }
}
