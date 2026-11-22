<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;

/**
 * How some store views write their script blocks, and whether the theme's own Product markup is still on their
 * product pages
 *
 * A layout setting is told only when every store view has the same one. The theme's markup is still there in a store
 * view when the last page test of a product page saw it, whatever the settings say; else it is removed when the
 * settings take it out; else, when a page test saw none, the theme writes none; else it is taken to be there, as the
 * default theme and the themes built on it write it. Over several store views it is still there when it is in any.
 */
class OutputFacts
{
    private const PRODUCT = 'product';

    /**
     * @param OutputSettings $settings
     * @param ThemeMarkupObservationsInterface $observations
     */
    public function __construct(
        private readonly OutputSettings $settings,
        private readonly ThemeMarkupObservationsInterface $observations
    ) {
    }

    /**
     * How the store views lay out their script blocks
     *
     * @param list<int> $storeIds
     * @return OutputFormat|null null when they differ, or there is no store view
     */
    public function format(array $storeIds): ?OutputFormat
    {
        $formats = [];
        foreach ($storeIds as $storeId) {
            $format = $this->settings->format($storeId);
            $formats[$format->value] = $format;
        }

        return count($formats) === 1 ? reset($formats) : null;
    }

    /**
     * Where the store views write their script blocks
     *
     * @param list<int> $storeIds
     * @return string|null the setting value of the place; null when they differ, or there is no store view
     */
    public function position(array $storeIds): ?string
    {
        $positions = [];
        foreach ($storeIds as $storeId) {
            $positions[$this->settings->position($storeId)] = true;
        }

        return count($positions) === 1 ? (string) array_key_first($positions) : null;
    }

    /**
     * Whether the theme's own Product markup is still on the store views' product pages
     *
     * @param list<int> $storeIds
     * @return ThemeMarkupState
     */
    public function themeMarkup(array $storeIds): ThemeMarkupState
    {
        $states = [];
        foreach ($storeIds as $storeId) {
            $states[] = $this->themeMarkupOf($storeId);
        }
        if ($states === [] || in_array(ThemeMarkupState::StillThere, $states, true)) {
            return ThemeMarkupState::StillThere;
        }

        return in_array(ThemeMarkupState::Removed, $states, true)
            ? ThemeMarkupState::Removed
            : ThemeMarkupState::NoneSeen;
    }

    /**
     * Whether the theme's own Product markup is still on one store view's product pages
     *
     * @param int $storeId
     * @return ThemeMarkupState
     */
    private function themeMarkupOf(int $storeId): ThemeMarkupState
    {
        $observation = $this->observations->latest($storeId, self::PRODUCT);
        if ($observation !== null && $observation->isFound()) {
            return ThemeMarkupState::StillThere;
        }
        if ($this->settings->removesThemeMarkup($storeId)) {
            return ThemeMarkupState::Removed;
        }

        return $observation === null ? ThemeMarkupState::StillThere : ThemeMarkupState::NoneSeen;
    }
}
