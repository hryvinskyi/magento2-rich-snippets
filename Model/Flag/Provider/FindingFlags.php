<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Provider;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagProviderInterface;
use Hryvinskyi\RichSnippets\Model\Check\FindingWording;

/**
 * Notes on a snippet each gap in the shop's data the last catalog check found in its blocks ("EAN is empty for 23
 * products"), worst first
 *
 * Only gaps worth fixing are noted; one that needs nothing is not. The number is the finding's pages, in the store
 * view, or in every store view each page once when none is picked. Before a check has finished there is nothing to
 * say.
 */
class FindingFlags implements SnippetFlagProviderInterface
{
    /**
     * @param CatalogCheckInterface $catalogCheck
     * @param FindingWording $wording
     * @param SnippetFlagFactoryInterface $flags
     */
    public function __construct(
        private readonly CatalogCheckInterface $catalogCheck,
        private readonly FindingWording $wording,
        private readonly SnippetFlagFactoryInterface $flags
    ) {
    }

    /**
     * @inheritDoc
     */
    public function flags(array $snippets, ?int $storeId): array
    {
        if ($snippets === []) {
            return [];
        }
        $listed = [];
        foreach ($snippets as $snippet) {
            $listed[$snippet->getCode()] = true;
        }
        $flags = [];
        foreach ($this->catalogCheck->findings($storeId) as $finding) {
            if ($finding->getTone() === PropertyTone::Note) {
                continue;
            }
            $text = (string) __(
                '%1 for %2',
                $this->wording->title($finding),
                $this->wording->count($finding, $finding->getPages())
            );
            foreach ($finding->getSnippetCodes() as $code) {
                if (isset($listed[$code])) {
                    $flags[$code][] = $this->flags->create(FlagTone::Note, $text);
                }
            }
        }

        return $flags;
    }
}
