<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview\Attention;

use Hryvinskyi\RichSnippets\Api\AttentionItemProviderInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTargetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTone;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;

/**
 * A library template with a newer version than the one a snippet of the scope was added from, an item that opens the
 * template's card in the library
 *
 * The snippet is never changed: the library card adds the new version beside it. One item per template, listed only
 * while the scope is live.
 */
class NewerTemplateAttention implements AttentionItemProviderInterface
{
    private const KEY_PREFIX = 'template-';
    private const ROUTE = 'hryvinskyi_richsnippets/library/index';

    /**
     * @param LibraryInterface $library
     * @param AttentionItemInterfaceFactory $items
     * @param AttentionTargetInterfaceFactory $targets
     */
    public function __construct(
        private readonly LibraryInterface $library,
        private readonly AttentionItemInterfaceFactory $items,
        private readonly AttentionTargetInterfaceFactory $targets
    ) {
    }

    /**
     * @inheritDoc
     */
    public function items(AttentionScopeInterface $scope): array
    {
        if ($scope->getSetupState() !== SetupState::Live) {
            return [];
        }
        $oldest = [];
        foreach ($scope->getSnippets() as $snippet) {
            $origin = $snippet->getOrigin();
            if ($origin !== null) {
                $oldest[$origin->getCode()] = min($oldest[$origin->getCode()] ?? PHP_INT_MAX, $origin->getVersion());
            }
        }
        $items = [];
        foreach ($this->library->list() as $entry) {
            $template = $entry->getTemplate();
            $code = $template->getCode();
            if (!$entry->hasNewerVersion() || ($oldest[$code] ?? PHP_INT_MAX) >= $template->getVersion()) {
                continue;
            }
            $items[] = $this->items->create([
                'key' => self::KEY_PREFIX . $code,
                'tone' => AttentionTone::Info,
                'title' => 'The %1 template has a newer version',
                'titleArguments' => [(string) __($template->getName())],
                'description' => 'Your snippet is unchanged. The library can add the new version beside it,'
                    . ' switched off.',
                'descriptionArguments' => [],
                'actionLabel' => 'Open the library',
                'target' => $this->targets->create(['route' => self::ROUTE, 'params' => ['template' => $code]]),
            ]);
        }

        return $items;
    }
}
