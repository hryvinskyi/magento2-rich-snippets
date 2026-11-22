<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Magento\Framework\Phrase;

/**
 * The Library island's own wording, translated when read: the page's fixed words around the server's templates
 *
 * Exactly the keys the island asks for (`Test/_contract/admin-pages/library-phrases.json`): `page.*` are the words
 * every Rich Snippets page shares, `library.*` the library's own. `%1` … `%n` are filled by the island; a count
 * sentence is one key per plural form of the admin's language (`.one`, `.other`, `.few` …), `%1` being the count. The
 * keys of `library.again.*` are template codes, so a template with no warning of its own takes `library.again.other`;
 * the keys of `library.refused.*` are the reasons the add endpoints give, so a reason the island has no words for falls
 * back to `library.refused.other`.
 */
class LibraryPhrases implements PagePhrasesInterface
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
            'page.loading' => __('Loading…'),
            'page.loadingLabel' => __('Loading'),
            'page.unavailable' => __('Not available'),
            'library.again.other' => __('You already have %1. The new copy is added beside it as “%2”.'),
            'library.again.product' => __(
                'You already have a Product snippet. A second one makes sense only with a condition that keeps the '
                . 'two apart, for example another attribute set. Otherwise product pages would output two Product '
                . 'blocks.'
            ),
            'library.blocks.have' => __('Uses the one you already have.'),
            'library.blocks.new' => __('Not in this shop yet: it is added too, and can be changed like any snippet.'),
            'library.blocks.title' => __('Building blocks'),
            'library.card.add' => __('Add'),
            'library.card.addAgain' => __('Add again'),
            'library.card.addAgainNamed' => __('Add %1 again'),
            'library.card.addNamed' => __('Add %1'),
            'library.card.added' => __('Added'),
            ...$this->plurals->forms(
                'library.card.subtitle',
                __('%1 ready-made snippet. It is added as a copy, switched off.'),
                __('%1 ready-made snippets. Each is added as a copy, switched off.')
            ),
            'library.card.title' => __('Templates'),
            'library.chips.all' => __('All'),
            'library.chips.label' => __('Show'),
            'library.drawer.add' => __('Add, switched off'),
            'library.drawer.addNewer' => __('Add the new version beside it'),
            'library.drawer.adding' => __('Adding…'),
            'library.drawer.block' => __('Building block'),
            'library.drawer.cancel' => __('Cancel'),
            'library.drawer.google' => __('On Google'),
            'library.drawer.newer' => __(
                'Your copy was added from an earlier version of this template. The new version is added beside it '
                . 'as “%1”, switched off; your copy stays as it is.'
            ),
            'library.drawer.template' => __('Template'),
            'library.drawer.title' => __('Add %1'),
            'library.drawer.where' => __('Where it is output'),
            'library.drawer.whereNote' => __('Pages, conditions and store views can be changed after adding.'),
            'library.drawer.writes' => __('What it writes'),
            'library.failed.add' => __('The template could not be added.'),
            'library.failed.addRecommended' => __('The recommended set could not be added.'),
            'library.failed.text' => __(
                'Your own snippets are not affected, and a blank snippet can still be started.'
            ),
            'library.failed.title' => __('The library could not be loaded'),
            'library.header.blank' => __('Blank snippet'),
            'library.hero.add' => __('Add the recommended set'),
            'library.hero.adding' => __('Adding…'),
            'library.hero.text' => __(
                'They cover the shop itself, the path to each page and every product. All arrive switched off, so '
                . 'nothing changes on the storefront until you have looked at them.'
            ),
            'library.hero.title' => __('Start with the five most shops need'),
            'library.how.copy' => __(
                'Adding a template makes a copy that is yours: change, rename or delete it freely.'
            ),
            'library.how.off' => __('A copy arrives switched off. Test it, then switch it on in Snippets.'),
            'library.how.title' => __('How templates work'),
            'library.how.update' => __(
                'When the library changes with a module update, your copies stay as they are. The Overview says '
                . 'which templates have a newer version.'
            ),
            'library.noMatch.blank' => __('Blank snippet'),
            'library.noMatch.clear' => __('Clear the search'),
            'library.noMatch.text' => __(
                'The library holds the snippets shops ask for most. Anything else on schema.org can be built from a '
                . 'blank snippet, or pasted into its Code tab.'
            ),
            'library.noMatch.title' => __('No template for “%1”'),
            'library.notIn.blank' => __('Blank snippet'),
            'library.notIn.text' => __(
                'A blank snippet takes any schema.org type and any property. A template found elsewhere can be '
                . 'pasted into its Code tab.'
            ),
            'library.notIn.title' => __('Not in the library?'),
            'library.questions.failed' => __(
                'The suggestions could not be loaded. Adding takes the answers the library suggests.'
            ),
            'library.questions.loading' => __('Reading the catalog…'),
            'library.questions.retry' => __('Try again'),
            'library.questions.text' => __(
                'Attributes differ from shop to shop. The suggestions come from the attribute codes and from how many '
                . 'products fill them.'
            ),
            'library.questions.title' => __('Where your shop keeps it'),
            'library.refused.other' => __('The copy breaks a rule every snippet must follow, so nothing was added.'),
            'library.refused.template_invalid' => __(
                'The template has a mistake this shop cannot compile, so nothing was added.'
            ),
            'library.search.label' => __('Search templates'),
            'library.toast.added' => __('%1 was added, switched off. Look at what it writes, then switch it on.'),
            'library.toast.addedBlock' => __(
                '%1 was added, switched off. Use it from any snippet: pick “Another snippet” as the value of a '
                . 'property.'
            ),
            'library.toast.open' => __('Open it'),
            'library.toast.openTest' => __('Open Test'),
            ...$this->plurals->forms(
                'library.toast.set',
                __(
                    '%1 snippet was added, switched off. Look at what it writes for a real page, then switch it on.'
                ),
                __(
                    '%1 snippets were added, all switched off. Look at what each one writes for a real page, then '
                    . 'switch them on.'
                )
            ),
            ...$this->plurals->forms(
                'library.toast.setBlocks',
                __(
                    '%2 snippets and the building block they share were added, all switched off. Look at what each one '
                    . 'writes for a real page, then switch them on.'
                ),
                __(
                    '%2 snippets and the %1 building blocks they share were added, all switched off. Look at what each '
                    . 'one writes for a real page, then switch them on.'
                )
            ),
            'library.toast.setNone' => __('The shop has every recommended snippet already. Nothing was added.'),
        ];

        return array_map(
            static fn (Phrase|string $phrase): string => $phrase instanceof Phrase ? $phrase->render() : $phrase,
            $phrases
        );
    }
}
