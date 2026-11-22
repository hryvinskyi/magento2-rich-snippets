<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetInUseException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Deletes a snippet no other snippet includes, and cleans the page cache of its pages once the delete is committed
 *
 * Any snippet can be included, so the refusal does not depend on whether the snippet is a building block.
 */
class DeleteSnippet
{
    /**
     * @param IncludeGraph $graph
     * @param IncludeEdgesInterface $edges
     * @param SnippetReaderInterface $reader
     * @param SnippetWriterInterface $writer
     * @param PageCacheCleanerInterface $cleaner
     */
    public function __construct(
        private readonly IncludeGraph $graph,
        private readonly IncludeEdgesInterface $edges,
        private readonly SnippetReaderInterface $reader,
        private readonly SnippetWriterInterface $writer,
        private readonly PageCacheCleanerInterface $cleaner
    ) {
    }

    /**
     * Delete a stored snippet
     *
     * @param SnippetInterface $snippet
     * @return void
     * @throws SnippetInUseException when another snippet includes it
     * @throws NoSuchEntityException when it is not stored
     */
    public function delete(SnippetInterface $snippet): void
    {
        $id = $snippet->getId();
        $this->writer->inTransaction(function () use ($id): void {
            $stored = $id === null ? null : $this->reader->byId($id);
            if ($id === null || $stored === null) {
                throw new NoSuchEntityException(__('The snippet with ID "%1" does not exist.', (string) $id));
            }
            $edges = $this->edges->edges();
            $includers = $this->graph->directIncludersOf($stored->getCode(), $edges);
            if ($includers !== []) {
                $names = [];
                foreach ($this->reader->byCodes($includers) as $includer) {
                    $names[$includer->getCode()] = $includer->getName();
                }
                throw new SnippetInUseException($stored->getCode(), $names);
            }
            $this->writer->remove($id);
            $this->cleaner->cleanAfterCommit($stored, null, $edges);
        });
    }
}
