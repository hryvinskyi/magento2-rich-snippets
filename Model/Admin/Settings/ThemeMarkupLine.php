<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Settings;

use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Check\RunTimes;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * The settings page's read-only "Found on the Storefront" line: what the last page test of a product page saw of the
 * theme's own Product markup in a store view
 *
 * With removal on and nothing seen since, the line says the markup is removed (`ok`). Markup seen on the last page
 * read is named by its properties, with when and where it was seen (`warn`), whatever the setting: a product page
 * that outputs no Product snippet keeps the theme's markup. A page read without markup while removal is off says
 * so (`ok`). Before any page test read a product page there is nothing to tell (`none`).
 *
 * @phpstan-type Line array{tone: string, text: string}
 */
class ThemeMarkupLine
{
    private const KIND = 'product';

    /**
     * @param OutputSettings $settings
     * @param ThemeMarkupObservationsInterface $observations
     * @param RunTimes $times
     * @param WordList $words
     */
    public function __construct(
        private readonly OutputSettings $settings,
        private readonly ThemeMarkupObservationsInterface $observations,
        private readonly RunTimes $times,
        private readonly WordList $words
    ) {
    }

    /**
     * The line of a store view
     *
     * @param int $storeId
     * @return Line
     */
    public function forStore(int $storeId): array
    {
        $observation = $this->observations->latest($storeId, self::KIND);
        $removes = $this->settings->removesThemeMarkup($storeId);
        if ($observation !== null && $observation->isFound()) {
            $properties = [];
            foreach ($observation->getItems() as $item) {
                array_push($properties, ...$item->getProperties());
            }

            return [
                'tone' => 'warn',
                'text' => (string) __(
                    'Product markup in the theme’s HTML: %1. Seen %2 on %3.',
                    $this->words->join(array_values(array_unique($properties))),
                    $this->times->at($observation->getSeenAt()),
                    $observation->getPath()
                ),
            ];
        }
        if ($removes) {
            return ['tone' => 'ok', 'text' => $this->removedText()];
        }
        if ($observation === null) {
            return ['tone' => 'none', 'text' => (string) __('No page test has looked at a product page yet.')];
        }

        return [
            'tone' => 'ok',
            'text' => (string) __(
                'Nothing. No Product markup in the theme’s HTML when %1 was read %2.',
                $observation->getPath(),
                $this->times->at($observation->getSeenAt())
            ),
        ];
    }

    /**
     * What the line says while the markup is removed and none was seen since
     *
     * @return string
     */
    private function removedText(): string
    {
        return (string) __(
            'Nothing. The theme’s Product markup is removed; each product is described once, by the Product snippet.'
        );
    }
}
