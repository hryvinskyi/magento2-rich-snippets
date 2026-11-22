<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Provider;

use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckRunStoreInterface;

/**
 * Warns on a snippet that the last catalog check found selected after another snippet of its type on the same
 * pages ("Second Product on the same pages"): those pages describe one thing twice
 *
 * The snippet that comes first in the output order carries no flag. Before a check has finished there is nothing to
 * say. The counts are the store view's, or every store view's when none is picked.
 */
class SameTypeOverlapFlags implements SnippetFlagProviderInterface
{
    private const EVERY_STORE_VIEW = 0;

    /**
     * @param CheckRunStoreInterface $runs
     * @param CheckResultReaderInterface $results
     * @param SnippetFlagFactoryInterface $flags
     */
    public function __construct(
        private readonly CheckRunStoreInterface $runs,
        private readonly CheckResultReaderInterface $results,
        private readonly SnippetFlagFactoryInterface $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function flags(array $snippets, ?int $storeId): array
    {
        $last = $this->runs->latest(CheckState::Done);
        if ($last === null || $snippets === []) {
            return [];
        }
        $counts = $this->results->aggregates($last->getId(), $storeId ?? self::EVERY_STORE_VIEW);
        $seconds = $counts[AggregateMetric::SecondOfType->value] ?? [];
        $flags = [];
        foreach ($snippets as $snippet) {
            if (($seconds[$snippet->getCode()] ?? 0) > 0) {
                $flags[$snippet->getCode()][] = $this->flags->create(
                    FlagTone::Warn,
                    (string) __('Second %1 on the same pages', $snippet->getType())
                );
            }
        }

        return $flags;
    }
}
