<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\Exception\StaleSnippetException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Saves a snippet under the snippet rules
 *
 * In this order: the snippet is checked against the rules of a snippet on its own; the template must compile; the
 * type is taken from the template's root; inside one transaction, a stored snippet keeps its code and is not left
 * with no store view while one it was set for still exists, the snippet may not include itself through the stored
 * snippets, it is written, and the page cache of the pages the change reaches is cleaned once the transaction
 * commits. Missing Google requirements never refuse a save.
 */
class SaveSnippet
{
    private const LOOP_SEPARATOR = ' → ';

    /**
     * @param TemplateEngineInterface $engine
     * @param TemplateTypeInterface $types
     * @param IncludeGraph $graph
     * @param IncludeEdgesInterface $edges
     * @param SnippetFactoryInterface $snippets
     * @param SnippetReaderInterface $reader
     * @param SnippetWriterInterface $writer
     * @param PageCacheCleanerInterface $cleaner
     * @param ChangeAuthorInterface $author
     * @param ExistingStoreViewsInterface $storeViews
     */
    public function __construct(
        private readonly TemplateEngineInterface $engine,
        private readonly TemplateTypeInterface $types,
        private readonly IncludeGraph $graph,
        private readonly IncludeEdgesInterface $edges,
        private readonly SnippetFactoryInterface $snippets,
        private readonly SnippetReaderInterface $reader,
        private readonly SnippetWriterInterface $writer,
        private readonly PageCacheCleanerInterface $cleaner,
        private readonly ChangeAuthorInterface $author,
        private readonly ExistingStoreViewsInterface $storeViews
    ) {
    }

    /**
     * Save a snippet: a new one when it has no ID, else over the stored one with its ID
     *
     * A save over a stored snippet can be made from a version of it: it is then refused when the stored snippet was
     * saved after that version. The stored update time is compared inside the save's transaction and again by the
     * write itself, so a save that races another can never pass on a version both started from.
     *
     * @param SnippetInterface $snippet
     * @param string|null $expectedUpdatedAt the update time of the version the save was made from; null saves over
     *     whatever is stored. Ignored for a new snippet.
     * @return SnippetRootInterface the snippet as stored
     * @throws CompileException when the template does not compile
     * @throws SnippetRuleException when the snippet breaks a snippet rule
     * @throws NoSuchEntityException when the snippet has an ID no stored snippet has
     * @throws StaleSnippetException when the stored snippet was saved after the version the save was made from
     */
    public function save(SnippetInterface $snippet, ?string $expectedUpdatedAt = null): SnippetRootInterface
    {
        $draft = $this->checked($snippet);
        $includes = $this->engine->compile($draft->getTemplate())->getIncludes();
        $draft->projectType($this->types);

        $write = function () use ($draft, $includes, $expectedUpdatedAt): SnippetRootInterface {
            $id = $draft->getId();
            $before = $id === null ? null : $this->stored($id);
            if ($before !== null && $expectedUpdatedAt !== null && $before->getUpdatedAt() !== $expectedUpdatedAt) {
                $this->refuseAsStale($before);
            }
            if ($before !== null && $before->getCode() !== $draft->getCode()) {
                throw new SnippetRuleException(SnippetRuleReason::CodeChanged, ['code' => $draft->getCode()]);
            }
            if ($before !== null) {
                $draft->assertKeepsStoreViewsOf($before, $this->storeViews);
            }
            $edges = $this->edges->edges();
            $loop = $this->graph->loopThrough($draft->getCode(), $includes, $edges);
            if ($loop !== []) {
                throw new SnippetRuleException(
                    SnippetRuleReason::IncludeLoop,
                    ['codes' => implode(self::LOOP_SEPARATOR, $loop)]
                );
            }

            if ($id === null) {
                $id = $this->writer->insert($draft, $this->author->name());
            } else {
                $this->writeOver($draft, $id, $expectedUpdatedAt);
            }
            $saved = $this->stored($id);
            $this->cleaner->cleanAfterCommit($before, $saved, $edges);

            return $saved;
        };

        return $this->writer->inTransaction($write);
    }

    /**
     * The snippet rebuilt from its values, which checks them against the rules of a snippet on its own
     *
     * @param SnippetInterface $snippet
     * @return SnippetRootInterface
     * @throws SnippetRuleException
     */
    private function checked(SnippetInterface $snippet): SnippetRootInterface
    {
        return $this->snippets->restore(
            $snippet->getId(),
            $snippet->getCode(),
            $snippet->getName(),
            $snippet->getTemplate(),
            $snippet->getType(),
            $snippet->isBlock(),
            $snippet->isActive(),
            $snippet->getPageKinds(),
            $snippet->getConditions(),
            $snippet->getStoreIds(),
            $snippet->getOrigin(),
            $snippet->getSortOrder(),
            $snippet->getUpdatedAt(),
            $snippet->getUpdatedBy()
        );
    }

    /**
     * Write a snippet over the stored one, refusing the write when the stored one was saved after the expected version
     *
     * @param SnippetRootInterface $snippet
     * @param int $id
     * @param string|null $expectedUpdatedAt
     * @return void
     * @throws StaleSnippetException
     * @throws NoSuchEntityException when the stored snippet is gone
     */
    private function writeOver(SnippetRootInterface $snippet, int $id, ?string $expectedUpdatedAt): void
    {
        if (!$this->writer->update($snippet, $this->author->name(), $expectedUpdatedAt)) {
            $this->refuseAsStale($this->stored($id));
        }
    }

    /**
     * Refuse a save made from an older version of a stored snippet
     *
     * @param SnippetInterface $stored
     * @return never
     * @throws StaleSnippetException
     */
    private function refuseAsStale(SnippetInterface $stored): never
    {
        throw new StaleSnippetException(
            (int) $stored->getId(),
            (string) $stored->getUpdatedAt(),
            $stored->getUpdatedBy()
        );
    }

    /**
     * The stored snippet with an ID
     *
     * @param int $id
     * @return SnippetRootInterface
     * @throws NoSuchEntityException
     */
    private function stored(int $id): SnippetRootInterface
    {
        $snippet = $this->reader->byId($id);
        if ($snippet === null) {
            throw new NoSuchEntityException(__('The snippet with ID "%1" does not exist.', $id));
        }

        return $snippet;
    }
}
