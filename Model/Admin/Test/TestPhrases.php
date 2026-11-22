<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Magento\Framework\Phrase;

/**
 * The Test island's own wording, translated when read: the page's fixed words around the server's test results, check
 * status and findings
 *
 * Exactly the keys the island asks for (`Test/_contract/admin-pages/test-phrases.json`): `page.*` are the words every
 * Rich Snippets page shares, `test.*` the page's own. `%1` … `%n` are filled by the island; a count sentence is one key
 * per plural form of the admin's language (`.one`, `.other`, `.few` …), `%1` being the count. `test.picker.kind.*` name
 * a page in the picker by its kind; a kind with no key of its own is named by its label alone.
 */
class TestPhrases implements PagePhrasesInterface
{
    /**
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(private readonly PluralPhrasesInterface $plurals)
    {
    }

    /**
     * @inheritDoc
     */
    public function getPhrases(): array
    {
        $phrases = [
            'page.store.label' => __('Store view'),
            'test.bar.line.busy' => __('Reading %1 as a visitor would.'),
            'test.bar.line.done' => __(
                'Tested just now, in %1 s. The page was read as a visitor gets it, page cache included.'
            ),
            'test.bar.line.error' => __('Tried just now: the test could not be run.'),
            'test.bar.line.failed' => __('Tried just now: the storefront did not answer.'),
            'test.bar.line.none' => __('Pick a page to test it.'),
            'test.bar.page' => __('Page'),
            'test.bar.run' => __('Test'),
            'test.bar.running' => __('Testing…'),
            'test.block.edit' => __('Edit this snippet'),
            ...$this->plurals->forms(
                'test.block.foot',
                __('%1 line · one script block'),
                __('%1 lines · one script block')
            ),
            'test.block.notOurs' => __('Not written by Rich Snippets'),
            'test.block.output' => __('%1 output'),
            'test.block.size' => __('%1 KB'),
            'test.check.failed' => __('The check could not be asked for.'),
            'test.check.leave' => __(
                'You can leave this page. The check goes on in the background and the findings appear here.'
            ),
            'test.check.progress' => __('Catalog check'),
            'test.check.run' => __('Check now'),
            'test.check.running' => __('Checking…'),
            'test.check.statusFailed' => __('Where the check stands could not be read.'),
            'test.drawer.download' => __('Download the list'),
            'test.drawer.downloadFailed' => __('The list could not be downloaded.'),
            'test.drawer.downloading' => __('Downloading…'),
            'test.drawer.failed' => __('The pages could not be loaded.'),
            'test.drawer.loading' => __('Loading the pages…'),
            'test.drawer.open' => __('Open them in Catalog › Products'),
            'test.drawer.page' => __('Page'),
            'test.drawer.product' => __('Product'),
            'test.drawer.sku' => __('SKU'),
            'test.drawer.table' => __('Pages'),
            'test.drawer.test' => __('Test'),
            'test.drawer.testNamed' => __('Test %1'),
            'test.error.text' => __('The test could not be run.'),
            'test.fail.retry' => __('Try again'),
            'test.fail.title' => __('The page could not be read'),
            'test.findings.checking' => __('Checking…'),
            'test.findings.cleanText' => __(
                'Every page the snippets are set for gets what Google requires, and no recommended value is missing.'
            ),
            'test.findings.cleanTitle' => __('Nothing found'),
            'test.findings.failed' => __('The findings could not be loaded.'),
            'test.findings.noneText' => __('The findings appear here after the first check.'),
            'test.findings.noneTitle' => __('No findings yet'),
            'test.findings.pages' => __('Pages'),
            'test.findings.snippet' => __('Snippet'),
            'test.findings.title' => __('Findings'),
            'test.findings.waitingText' => __(
                'The findings of the last run stay on the Overview until this one is finished.'
            ),
            'test.findings.waitingTitle' => __('Checking'),
            'test.findings.what' => __('What'),
            'test.google.open' => __('Open in the Rich Results Test'),
            'test.google.text' => __(
                'This test compares the page with the rules Google publishes. Google’s own test reads the live page '
                . 'and has the last word. It cannot reach a shop behind a login or on a local address.'
            ),
            'test.google.title' => __('Ask Google itself'),
            'test.how.builds' => __(
                'It builds every snippet that is on for every page it is set for, in each store view, without '
                . 'visiting the storefront.'
            ),
            'test.how.data' => __(
                'A finding is about the shop’s data, such as a product without an image. The snippet is rarely the '
                . 'thing to change.'
            ),
            'test.how.title' => __('How the check works'),
            'test.mode.catalog' => __('Whole catalog'),
            'test.mode.label' => __('Test'),
            'test.mode.page' => __('One page'),
            'test.notOutput.conditions' => __('See the conditions'),
            ...$this->plurals->forms(
                'test.notOutput.summary',
                __('%1 snippet is not output here'),
                __('%1 snippets are not output here')
            ),
            'test.nothing.address' => __(
                'No snippet is set for this address. That is usual: a page such as the cart is not one search '
                . 'engines list. The reasons are below, snippet by snippet.'
            ),
            'test.nothing.none' => __('No snippet writes anything on this page, and none is set up for it.'),
            'test.nothing.page' => __(
                'No snippet writes anything on this page. The reasons are below, snippet by snippet.'
            ),
            'test.nothing.title' => __('Nothing is output on this page'),
            'test.output.busy' => __('Testing'),
            'test.output.subtitle' => __('%1 · %2'),
            'test.output.subtitleNothing' => __('%1 · nothing from Rich Snippets'),
            'test.output.subtitleProblems' => __('%1 · %2 · %3'),
            ...$this->plurals->forms(
                'test.output.blocks',
                __('%1 script block, %2 KB'),
                __('%1 script blocks, %2 KB')
            ),
            ...$this->plurals->forms(
                'test.output.problems',
                __('%1 with a problem'),
                __('%1 with a problem')
            ),
            'test.output.title' => __('What this page outputs'),
            'test.picker.failed' => __('The pages could not be searched.'),
            'test.picker.kind.category' => __('Category · %1'),
            'test.picker.kind.cms' => __('CMS page · %1'),
            'test.picker.kind.other' => __('Any address · %1'),
            'test.picker.kind.product' => __('Product · %1'),
            'test.picker.none' => __('No page has this name. A storefront address starts with /.'),
            'test.picker.placeholder' => __('Find a page by its name, SKU or address'),
            'test.picker.results' => __('Pages'),
            'test.picker.searching' => __('Searching…'),
            'test.req.leftOut' => __('Left out'),
            'test.req.met' => __('Met'),
            'test.req.notMet' => __('Not met'),
            'test.req.nothing' => __('Nothing to compare: the page outputs no snippet.'),
            'test.req.testing' => __('Testing…'),
            'test.req.title' => __('Against Google’s requirements'),
            'test.req.unread' => __('Nothing to compare: the page was not read.'),
            'test.theme.heading' => __('Also on this page, not written by Rich Snippets'),
            'test.theme.remove' => __('Remove it in Settings'),
        ];

        return array_map(
            static fn (Phrase|string $phrase): string => $phrase instanceof Phrase ? $phrase->render() : $phrase,
            $phrases
        );
    }
}
