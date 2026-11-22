<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\RichSnippets\Model\Overview\OutputFacts;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Where a snippet that is on writes its script block, as the store views' “Write the Snippets” setting says, in the
 * words of the admin pages: the first line of the Snippets page's "How snippets are output" and the note of the
 * editor's preview — the texts the position pool has for the place — or texts naming no place when the store views
 * differ
 */
class OutputLine
{
    /**
     * @param OutputFacts $outputFacts
     * @param StoreManagerInterface $storeManager
     * @param OutputPositionPool $positions
     */
    public function __construct(
        private readonly OutputFacts $outputFacts,
        private readonly StoreManagerInterface $storeManager,
        private readonly OutputPositionPool $positions
    ) {
    }

    /**
     * The line, translated
     *
     * @return string
     */
    public function line(): string
    {
        $position = $this->position();

        return $position === null
            ? (string) __(
                'Each snippet that is on writes one JSON-LD script block into the pages it is set for, where the '
                . 'store view’s “Write the Snippets” setting puts it.'
            )
            : (string) __($this->positions->line($position));
    }

    /**
     * The note of the editor's preview of a page snippet, translated
     *
     * @return string
     */
    public function previewNote(): string
    {
        $position = $this->position();

        return $position === null
            ? (string) __('One script block where the store view’s “Write the Snippets” setting puts it')
            : (string) __($this->positions->note($position));
    }

    /**
     * Where every store view writes the blocks; null when they differ
     *
     * @return string|null the position setting's value
     */
    private function position(): ?string
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeIds[] = (int) $store->getId();
        }

        return $this->outputFacts->position($storeIds);
    }
}
