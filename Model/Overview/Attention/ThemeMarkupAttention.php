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
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;

/**
 * The theme's own Product markup still on the product pages, an item that opens the module's settings
 *
 * It is listed whether the snippets are on or off, once anything is set up: product pages carrying the theme's
 * description beside a Product snippet describe the same product twice.
 */
class ThemeMarkupAttention implements AttentionItemProviderInterface
{
    private const KEY = 'theme-markup';
    private const ROUTE = 'adminhtml/system_config/edit';
    private const SECTION = 'hryvinskyi_rich_snippets';

    /**
     * @param AttentionItemInterfaceFactory $items
     * @param AttentionTargetInterfaceFactory $targets
     */
    public function __construct(
        private readonly AttentionItemInterfaceFactory $items,
        private readonly AttentionTargetInterfaceFactory $targets
    ) {
    }

    /**
     * @inheritDoc
     */
    public function items(AttentionScopeInterface $scope): array
    {
        if ($scope->getSetupState() === SetupState::None || $scope->getThemeMarkup() !== ThemeMarkupState::StillThere) {
            return [];
        }
        $storeId = $scope->getStoreId();
        $params = ['section' => self::SECTION];

        return [$this->items->create([
            'key' => self::KEY,
            'tone' => AttentionTone::Warn,
            'title' => 'The theme still writes its own Product markup',
            'titleArguments' => [],
            'description' => 'Product pages carry two descriptions of the same product, which can disagree.',
            'descriptionArguments' => [],
            'actionLabel' => 'Open Settings',
            'target' => $this->targets->create([
                'route' => self::ROUTE,
                'params' => $storeId === null ? $params : $params + ['store' => (string) $storeId],
            ]),
        ])];
    }
}
