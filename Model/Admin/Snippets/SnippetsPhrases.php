<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Magento\Framework\Phrase;

/**
 * The Snippets island's own wording, translated when read: the page's fixed words around the server's snippet list
 *
 * Exactly the keys the island asks for (`Test/_contract/admin-pages/snippets-phrases.json`): `page.*` are the words
 * every Rich Snippets page shares, `snippets.*` the list's own. `%1` … `%n` are filled by the island; a count sentence
 * is one key per plural form of the admin's language (`.one`, `.other`, `.few` …), `%1` being the count. The keys of
 * `snippets.import.refused.*`, `snippets.import.problem.*` and `snippets.refused.*` are the reasons the endpoints give,
 * so a reason the island has no words for falls back to the `other` key of its group.
 */
class SnippetsPhrases implements PagePhrasesInterface
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
            'page.store.all' => __('All store views'),
            'page.store.label' => __('Store view'),
            'page.unavailable' => __('Not available'),
            'snippets.blocks.inside' => __('Building block: no page of its own. Inside %1.'),
            'snippets.blocks.unused' => __('Building block: no page of its own. No snippet uses it yet.'),
            ...$this->plurals->forms(
                'snippets.blocks.usedIn',
                __('In %1 snippet'),
                __('In %1 snippets')
            ),
            ...$this->plurals->forms(
                'snippets.card.blocks',
                __('%1 building block'),
                __('%1 building blocks')
            ),
            'snippets.card.empty' => __('Nothing is output.'),
            'snippets.card.subtitle' => __('%1 on, %2.'),
            'snippets.card.subtitleOff' => __('%1 on, %2 off, %3.'),
            'snippets.card.title' => __('Snippets'),
            'snippets.chips.all' => __('All'),
            'snippets.chips.attention' => __('Needs attention'),
            'snippets.chips.blocks' => __('Building blocks'),
            'snippets.chips.label' => __('Show'),
            'snippets.delete.askOff' => __(
                'Delete %1? It is off, so nothing changes on the storefront. This cannot be undone; export it first '
                . 'to keep a copy.'
            ),
            'snippets.delete.askOn' => __(
                'Delete %1? Its pages stop writing it from the next page view. This cannot be undone; export it '
                . 'first to keep a copy.'
            ),
            'snippets.delete.close' => __('Close'),
            'snippets.delete.confirm' => __('Delete'),
            ...$this->plurals->forms(
                'snippets.delete.inUse',
                __(
                    '%2 cannot be deleted while %1 snippet uses it: %3. Take it out of that snippet first, or point it '
                    . 'at another building block.'
                ),
                __(
                    '%2 cannot be deleted while %1 snippets use it: %3. Take it out of them first, or point them at '
                    . 'another building block.'
                )
            ),
            'snippets.delete.keep' => __('Keep it'),
            'snippets.empty.blank' => __('Blank snippet'),
            'snippets.empty.library' => __('Start from the library'),
            'snippets.empty.text' => __(
                'The module writes nothing into the storefront until a snippet is added and switched on. The library '
                . 'has ready-made ones for products, breadcrumbs and the shop itself.'
            ),
            'snippets.empty.title' => __('No snippets yet'),
            'snippets.failed.delete' => __('The snippet could not be deleted.'),
            'snippets.failed.duplicate' => __('The snippet could not be duplicated.'),
            'snippets.failed.export' => __('The export could not be made.'),
            'snippets.failed.import' => __('The file could not be imported. Nothing was changed.'),
            'snippets.failed.preview' => __('The file could not be read. Nothing was changed.'),
            'snippets.failed.switch' => __('The switch could not be changed. It is back as it was.'),
            'snippets.failed.text' => __('The storefront keeps writing them as they were last saved.'),
            'snippets.failed.title' => __('The snippets could not be loaded'),
            'snippets.header.blank' => __('Blank snippet'),
            'snippets.header.library' => __('Add from the library'),
            'snippets.import.cancel' => __('Cancel'),
            'snippets.import.choice.add' => __('Add'),
            'snippets.import.choice.keep_both' => __('Keep both'),
            'snippets.import.choice.replace' => __('Replace'),
            'snippets.import.choice.skip' => __('Skip'),
            'snippets.import.choose' => __('Choose a file'),
            'snippets.import.chooseAnother' => __('Choose another'),
            'snippets.import.effect.add' => __('Added, switched off. Set for %1.'),
            'snippets.import.effect.addBlock' => __('Added as a building block.'),
            'snippets.import.effect.addNowhere' => __('Added, switched off. It is set for no kind of page yet.'),
            'snippets.import.effect.addWhere' => __('Added, switched off. Set for %1 where %2.'),
            'snippets.import.effect.keepBoth' => __('Added as “%1 (imported)”, switched off.'),
            'snippets.import.effect.keepBothBlock' => __('Added beside it as “%1 (imported)”.'),
            'snippets.import.effect.replaceBlock' => __('The one here is overwritten.'),
            'snippets.import.effect.replaceOff' => __('The one here is overwritten. It stays off.'),
            'snippets.import.effect.replaceOn' => __('The one here is overwritten. It stays on.'),
            'snippets.import.effect.skipExisting' => __('Left as it is here.'),
            'snippets.import.effect.skipNew' => __('Not imported.'),
            ...$this->plurals->forms(
                'snippets.import.file.count',
                __('%1 snippet'),
                __('%1 snippets')
            ),
            'snippets.import.file.exported' => __('exported %1'),
            'snippets.import.file.exportedFrom' => __('exported %1 from %2'),
            'snippets.import.file.kilobytes' => __('%1 KB'),
            'snippets.import.file.label' => __('Import file'),
            'snippets.import.file.none' => __('No file chosen yet. Pick an export of this module.'),
            'snippets.import.inFile' => __('In the file'),
            'snippets.import.note' => __(
                'A snippet that is added arrives switched off. A replaced one keeps its switch, its store views and '
                . 'its place in the list.'
            ),
            'snippets.import.problem.condition_unresolved' => __(
                'A condition names %1, which this shop does not have.'
            ),
            'snippets.import.problem.include_loop' => __('It would use itself: %1.'),
            'snippets.import.problem.include_missing' => __('It uses %1, which neither this shop nor the file has.'),
            'snippets.import.problem.other' => __('It breaks a rule every snippet must follow.'),
            'snippets.import.problem.store_unknown' => __('It is set for a store view this shop does not have: %1.'),
            'snippets.import.problem.template_invalid' => __('Its template has a mistake at %1: %2'),
            'snippets.import.reading' => __('Reading the file…'),
            'snippets.import.refused.changed' => __(
                'The shop or the file changed since the file was read. Choose the file again. Nothing was changed.'
            ),
            'snippets.import.refused.code_repeated' => __(
                'Two snippets in the file have the same code, so it is not an export of this module as written. '
                . 'Nothing was changed.'
            ),
            'snippets.import.refused.file_too_large' => __(
                'The file is larger than 2 MB, far larger than an export of this module. Nothing was changed.'
            ),
            'snippets.import.refused.not_an_export' => __(
                'This is not a Rich Snippets export. The file is valid JSON, but it has no “snippets” list. An export '
                . 'from this module starts with “hryvinskyi_rich_snippets”. Nothing was changed.'
            ),
            'snippets.import.refused.not_json' => __(
                'This file is not JSON, so it cannot be a Rich Snippets export. Nothing was changed.'
            ),
            'snippets.import.refused.other' => __('The file could not be imported. Nothing was changed.'),
            'snippets.import.refused.snippet_malformed' => __(
                'A snippet in the file lacks a value or has one of the wrong kind. Nothing was changed.'
            ),
            'snippets.import.refused.too_deep' => __(
                'The file nests far deeper than an export of this module. Nothing was changed.'
            ),
            'snippets.import.refused.version_unsupported' => __(
                'The file was exported by a newer version of the module, which this shop cannot read yet. Nothing '
                . 'was changed.'
            ),
            'snippets.import.run' => __('Import'),
            ...$this->plurals->forms(
                'snippets.import.runCount',
                __('Import %1 snippet'),
                __('Import %1 snippets')
            ),
            'snippets.import.running' => __('Importing…'),
            'snippets.import.status.changed' => __('Exists here. Changed here on %1, in the file on %2.'),
            'snippets.import.status.changedUndated' => __('Exists here, and the file holds another version of it.'),
            'snippets.import.status.identical' => __('Identical to the one here. Nothing to do.'),
            'snippets.import.status.invalid' => __('It cannot be imported.'),
            'snippets.import.status.new' => __('Not in this shop yet.'),
            'snippets.import.sub' => __('From a file exported here or in another shop.'),
            'snippets.import.title' => __('Import snippets'),
            'snippets.menu.delete' => __('Delete'),
            'snippets.menu.duplicate' => __('Duplicate'),
            'snippets.menu.export' => __('Export'),
            'snippets.menu.more' => __('More for %1'),
            'snippets.move.export' => __('Export all'),
            'snippets.move.import' => __('Import'),
            'snippets.move.text' => __(
                'Export writes every snippet, with its conditions, into one file. Import reads such a file and asks '
                . 'before it replaces anything.'
            ),
            'snippets.move.title' => __('Move snippets between shops'),
            'snippets.noMatch.attention' => __('No snippet needs attention.'),
            'snippets.noMatch.blocks' => __('There is no building block here.'),
            'snippets.noMatch.kind' => __('No snippet is set for %1.'),
            'snippets.noMatch.kindIn' => __('No snippet is set for %1 in %2.'),
            'snippets.noMatch.reset' => __('Show all snippets'),
            'snippets.noMatch.store' => __('No snippet is output in %1.'),
            'snippets.noMatch.title' => __('Nothing is output there'),
            'snippets.output.block' => __(
                'A building block is written inside the snippets that use it, never by itself.'
            ),
            'snippets.output.refresh' => __(
                'A change shows from the next page view: saving refreshes the page cache of the pages concerned.'
            ),
            'snippets.output.title' => __('How snippets are output'),
            'snippets.refused.block_cannot_be_switched_on' => __(
                'A building block has no switch: it is written inside the snippets that use it.'
            ),
            'snippets.refused.other' => __(
                'The change was refused: the snippet breaks a rule every snippet must follow.'
            ),
            'snippets.refused.stores_missing' => __(
                'Pick a store view for it first: every store view it was set for has been deleted.'
            ),
            'snippets.refused.template_invalid' => __(
                'The change was refused: the snippet’s template has a mistake. Open it in the editor to fix it.'
            ),
            'snippets.refused.unknown_snippet' => __('This snippet no longer exists. The list is up to date again.'),
            'snippets.row.allViews' => __('All store views'),
            'snippets.row.changed' => __('changed %1 by %2'),
            'snippets.row.changedAt' => __('changed %1'),
            'snippets.row.noKinds' => __('Set for no kind of page, so it is output nowhere.'),
            'snippets.row.noViews' => __('No store view'),
            ...$this->plurals->forms(
                'snippets.row.properties',
                __('%1 property'),
                __('%1 properties')
            ),
            'snippets.row.someViews' => __('%1 only'),
            'snippets.row.uses' => __('uses %1'),
            'snippets.row.where' => __('%1, where %2'),
            'snippets.store.allLine' => __('Every snippet, whichever store view it is for.'),
            'snippets.store.oneLine' => __('Only what %1 outputs. A snippet set for other store views is hidden.'),
            'snippets.switch.off' => __('Off'),
            'snippets.switch.on' => __('On'),
            'snippets.toast.deleted' => __('%1 was deleted.'),
            'snippets.toast.duplicated' => __('%1 was duplicated. The copy is switched off.'),
            'snippets.toast.exported' => __('%1 was exported as %2.'),
            ...$this->plurals->forms(
                'snippets.toast.exportedAll',
                __('%1 snippet was exported as %2.'),
                __('%1 snippets were exported as %2.')
            ),
            ...$this->plurals->forms(
                'snippets.toast.imported',
                __('%1 snippet was imported from %2. What was added is switched off: check it, then switch it on.'),
                __('%1 snippets were imported from %2. What was added is switched off: check it, then switch it on.')
            ),
        ];

        return array_map(
            static fn (Phrase|string $phrase): string => $phrase instanceof Phrase ? $phrase->render() : $phrase,
            $phrases
        );
    }
}
