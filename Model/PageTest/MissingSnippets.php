<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenReason;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenSnippetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\SnippetOutputInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;

/**
 * Says why the snippets a page does not carry are not there, worked out as the storefront of the page's store view
 * works it out, for a guest
 *
 * A page snippet not set for the page has the selection's reason. A snippet set for the page that the page as read
 * does not carry is missing because snippets are switched off for the store view, or because its value is empty on
 * the page (or fails), or, when the storefront writes it now, because the page read is an older copy.
 *
 * @phpstan-type Missing array{exclusions: list<SnippetExclusionInterface>, unwritten: list<UnwrittenSnippetInterface>}
 */
class MissingSnippets
{
    /**
     * @param SnippetSelectorInterface $selector
     * @param SnippetOutputInterface $output
     * @param FrontendEmulationInterface $emulation
     * @param OutputSettings $settings
     * @param UnwrittenSnippetInterfaceFactory $unwritten
     */
    public function __construct(
        private readonly SnippetSelectorInterface $selector,
        private readonly SnippetOutputInterface $output,
        private readonly FrontendEmulationInterface $emulation,
        private readonly OutputSettings $settings,
        private readonly UnwrittenSnippetInterfaceFactory $unwritten
    ) {
    }

    /**
     * The snippets a page does not carry, with the reasons
     *
     * @param PageEntityInterface $page
     * @param list<string> $carried the codes of the blocks the page carries
     * @return Missing
     */
    public function of(PageEntityInterface $page, array $carried): array
    {
        return $this->emulation->run(
            $page->getStoreId(),
            fn (): array => [
                'exclusions' => $this->selector->explain($page),
                'unwritten' => $this->unwrittenOn($page, array_flip($carried)),
            ]
        );
    }

    /**
     * The snippets set for a page that it does not carry
     *
     * @param PageEntityInterface $page
     * @param array<string,int> $carried by code
     * @return list<UnwrittenSnippetInterface>
     */
    private function unwrittenOn(PageEntityInterface $page, array $carried): array
    {
        $missing = [];
        foreach ($this->selector->select($page) as $snippet) {
            if (!isset($carried[$snippet->getCode()])) {
                $missing[] = $snippet;
            }
        }
        if ($missing === []) {
            return [];
        }
        $enabled = $this->settings->isEnabled($page->getStoreId());
        $written = [];
        foreach ($enabled ? $this->output->forPage($page) : [] as $block) {
            $written[$block->getCode()] = true;
        }
        $unwritten = [];
        foreach ($missing as $snippet) {
            $reason = match (true) {
                !$enabled => UnwrittenReason::SnippetsOff,
                isset($written[$snippet->getCode()]) => UnwrittenReason::NotInPage,
                default => UnwrittenReason::Empty,
            };
            $unwritten[] = $this->unwritten->create(['snippet' => $snippet, 'reason' => $reason]);
        }

        return $unwritten;
    }
}
