<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Magento\Framework\Phrase;

/**
 * The Overview island's own wording, translated when read: the page's fixed words around the server's Overview
 *
 * Exactly the keys the island asks for (`Test/_contract/admin-pages/overview-phrases.json`): `page.*` are the words
 * every Rich Snippets page shares, `overview.*` the Overview's own. `%1` … `%n` are filled by the island.
 */
class OverviewPhrases implements PagePhrasesInterface
{
    /**
     * @inheritDoc
     */
    public function getPhrases(): array
    {
        $phrases = [
            'overview.attention.title' => __('Needs attention'),
            'overview.features.failedText' => __(
                'The snippets themselves are not affected: the storefront keeps writing them.'
            ),
            'overview.features.failedTitle' => __('The numbers could not be loaded'),
            'overview.features.title' => __('What Google can show'),
            'overview.hero.blank' => __('Blank snippet'),
            'overview.hero.library' => __('Start from the library'),
            'overview.hero.step1.text' => __(
                '%1, %2 and %3 cover most shops. Each asks at most three questions.',
                __('Product'),
                __('Breadcrumbs'),
                __('Organization')
            ),
            'overview.hero.step1.title' => __('Pick templates'),
            'overview.hero.step2.text' => __('Test shows the output for a real product, next to what Google requires.'),
            'overview.hero.step2.title' => __('Look at what they write'),
            'overview.hero.step3.text' => __(
                'One switch per snippet. Off again takes one click, and nothing else in the shop changes.'
            ),
            'overview.hero.step3.title' => __('Switch them on'),
            'overview.hero.text' => __(
                'A snippet tells search engines what a page is about: a product with its price, the path to a '
                . 'category, the shop itself. The module writes nothing into the storefront until one is added '
                . 'and switched on.'
            ),
            'overview.hero.title' => __('Nothing is output yet'),
            'overview.kinds.see' => __('See one'),
            'overview.kinds.title' => __('What each kind of page outputs'),
            'overview.source.check' => __(
                'The catalog check builds every snippet for every page each night and counts what comes out.'
            ),
            'overview.source.limits' => __(
                'It knows what the shop writes, not what Google does with it. Search Console shows what '
                . 'Google has read.'
            ),
            'overview.source.title' => __('Where the numbers come from'),
            'overview.store.allLine' => __('Every store view together.'),
            'overview.store.oneLine' => __('What %1 outputs. Numbers are for its pages only.'),
            'page.loading' => __('Loading…'),
            'page.loadingLabel' => __('Loading'),
            'page.store.all' => __('All store views'),
            'page.store.label' => __('Store view'),
            'page.storefront' => __('View storefront'),
            'page.unavailable' => __('Not available'),
        ];

        return array_map(static fn (Phrase $phrase): string => $phrase->render(), $phrases);
    }
}
