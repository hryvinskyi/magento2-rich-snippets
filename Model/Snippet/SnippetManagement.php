<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\SnippetManagementInterface;
use Hryvinskyi\RichSnippets\Model\Idempotency\IdempotentWriteInterface;
use Hryvinskyi\RichSnippets\Model\Library\CopyNames;

/**
 * Switches, copies and deletes snippets through the snippet repository, whose save and delete keep the snippet
 * rules and clean the page cache once committed
 *
 * A copy keeps the original's template, kind (page snippet or building block), placement and place in the order; it
 * does not keep the library template the original was added from, since the copy was not added from the library.
 */
class SnippetManagement implements SnippetManagementInterface
{
    private const ACTION_DUPLICATE = 'snippet_duplicate';
    private const RESULT_ID = 'id';

    /**
     * @param SnippetRootRepositoryInterface $repository
     * @param SnippetFactoryInterface $snippets
     * @param CodeAllocatorInterface $codes
     * @param CopyNames $names
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     * @param IdempotentWriteInterface $idempotentWrite
     */
    public function __construct(
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly SnippetFactoryInterface $snippets,
        private readonly CodeAllocatorInterface $codes,
        private readonly CopyNames $names,
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary,
        private readonly IdempotentWriteInterface $idempotentWrite
    ) {
    }

    /**
     * @inheritDoc
     */
    public function setActive(int $id, bool $active): SnippetInterface
    {
        $snippet = $this->repository->get($id);
        if ($active) {
            $snippet->switchOn();
        } else {
            $snippet->switchOff();
        }

        return $this->repository->save($snippet);
    }

    /**
     * @inheritDoc
     */
    public function duplicate(int $id, string $idempotencyKey): SnippetInterface
    {
        $result = $this->idempotentWrite->once(
            $idempotencyKey,
            self::ACTION_DUPLICATE,
            function () use ($id): callable {
                $original = $this->repository->get($id);

                return fn (): array => [self::RESULT_ID => (int) $this->copy($original)->getId()];
            }
        );
        $copyId = $result[self::RESULT_ID] ?? null;

        return $this->repository->get(is_numeric($copyId) ? (int) $copyId : 0);
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): void
    {
        $this->repository->delete($this->repository->get($id));
    }

    /**
     * Save a switched-off copy of a snippet
     *
     * The copy is placed as the original is; a copy of a snippet set for no store view (every store view it was set
     * for was deleted) is a new draft, set for every store view like any other draft.
     *
     * @param SnippetRootInterface $original
     * @return SnippetRootInterface the copy as stored
     */
    private function copy(SnippetRootInterface $original): SnippetRootInterface
    {
        $taken = [];
        foreach ($this->repository->list() as $snippet) {
            $taken[$snippet->getName()] = true;
        }
        $name = $this->names->next((string) __('%1 (copy)', $original->getName()), $taken);
        $copy = $this->snippets->draft($this->codes->allocate($name), $name, $original->isBlock());
        $copy->changeTemplate($original->getTemplate(), $this->types);
        if (!$original->isBlock()) {
            $copy->place(
                $original->getPageKinds(),
                $original->getConditions(),
                $original->getStoreIds() === [] ? $copy->getStoreIds() : $original->getStoreIds(),
                $this->vocabulary
            );
        }
        $copy->moveTo($original->getSortOrder());

        return $this->repository->save($copy);
    }
}
