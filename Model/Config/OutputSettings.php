<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The settings that decide what a store view's pages write: whether the snippets are on, where and how their script
 * blocks are written, whether each is marked in the page source, and whether the theme's product markup goes
 *
 * A stored value outside a setting's options reads as the setting's default: for the position, a value no place in
 * the position pool has.
 */
class OutputSettings
{
    private const ENABLED = 'hryvinskyi_rich_snippets/general/enabled';
    private const POSITION = 'hryvinskyi_rich_snippets/output/position';
    private const FORMAT = 'hryvinskyi_rich_snippets/output/format';
    private const COMMENTS = 'hryvinskyi_rich_snippets/developer/comments';
    private const REMOVE_THEME_MARKUP = 'hryvinskyi_rich_snippets/theme/remove_product_markup';
    private const DEFAULT_POSITION = 'head';

    /**
     * @param ScopeConfigInterface $config
     * @param OutputPositionPool $positions
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly OutputPositionPool $positions
    ) {
    }

    /**
     * Whether the store view's pages write snippets at all
     *
     * @param int $storeId
     * @return bool
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->config->isSetFlag(self::ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Where the store view's pages write the script blocks: the setting value of a place in the position pool
     *
     * @param int $storeId
     * @return string
     */
    public function position(int $storeId): string
    {
        $stored = $this->text(self::POSITION, $storeId);

        return $this->positions->has($stored) ? $stored : self::DEFAULT_POSITION;
    }

    /**
     * How the JSON of the store view's script blocks is laid out
     *
     * @param int $storeId
     * @return OutputFormat
     */
    public function format(int $storeId): OutputFormat
    {
        return OutputFormat::tryFrom($this->text(self::FORMAT, $storeId)) ?? OutputFormat::Compact;
    }

    /**
     * Whether each script block is marked with its snippet's code in the page source
     *
     * @param int $storeId
     * @return bool
     */
    public function marksBlocks(int $storeId): bool
    {
        return $this->config->isSetFlag(self::COMMENTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether the theme's own product markup is taken out of the store view's product pages
     *
     * @param int $storeId
     * @return bool
     */
    public function removesThemeMarkup(int $storeId): bool
    {
        return $this->config->isSetFlag(self::REMOVE_THEME_MARKUP, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * A setting's stored text in a store view; empty when it has none
     *
     * @param string $path
     * @param int $storeId
     * @return string
     */
    private function text(string $path, int $storeId): string
    {
        $value = $this->config->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return is_string($value) ? $value : '';
    }
}
