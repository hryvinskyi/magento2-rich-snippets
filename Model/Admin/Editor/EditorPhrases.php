<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminUi\Api\Page\PagePhrasesInterface;
use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\OutputLine;
use Magento\Framework\Phrase;

/**
 * The snippet editor's own wording, translated when read: the header, the Snippet and Properties cards, the property
 * drawer, the Pages section, the Code tab, the Preview and Checks panels and the messages after a save, around what the
 * server answers
 *
 * Exactly the keys the island asks for (`Test/_contract/admin-pages/editor-phrases.json`): `page.*` are the words every
 * Rich Snippets page shares, `editor.*` the editor's own. `%1` … `%n` are filled by the island; a count sentence is one
 * key per plural form of the admin's language (`.one`, `.other`, `.few` …), `%1` being the count. The preview's note on
 * a page snippet names where the store views write the block (see OutputLine).
 */
class EditorPhrases implements PagePhrasesInterface
{
    /**
     * @param OutputLine $outputLine
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(
        private readonly OutputLine $outputLine,
        private readonly PluralPhrasesInterface $plurals
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPhrases(): array
    {
        $phrases = [
            ...$this->plurals->forms(
                'editor.checks.errors',
                __('%1 requirement missing'),
                __('%1 requirements missing')
            ),
            'editor.checks.foot' => __(
                'Compared with what Google publishes for each result type. Google decides whether a rich result '
                . 'is shown.'
            ),
            'editor.checks.none' => __('Nothing to check on this page.'),
            'editor.checks.title' => __('Checks'),
            ...$this->plurals->forms(
                'editor.checks.warnings',
                __('%1 warning'),
                __('%1 warnings')
            ),
            'editor.chip.as' => __('as %1'),
            'editor.chip.customCode' => __('Custom code'),
            'editor.chip.else' => __('else “%1”'),
            'editor.chip.enteredHere' => __('entered here'),
            ...$this->plurals->forms(
                'editor.chip.items',
                __('%1 item'),
                __('%1 items')
            ),
            ...$this->plurals->forms(
                'editor.chip.lines',
                __('%1 line'),
                __('%1 lines')
            ),
            'editor.chip.newest' => __('the newest %1'),
            'editor.chip.noType' => __('No type yet'),
            'editor.chip.nothingTyped' => __('Nothing typed yet'),
            'editor.chip.oneForEach' => __('One for each %1'),
            'editor.chip.pickField' => __('Pick a field'),
            ...$this->plurals->forms(
                'editor.chip.properties',
                __('%1 property'),
                __('%1 properties')
            ),
            'editor.chip.quoted' => __('“%1”'),
            'editor.chip.schemaValue' => __('schema.org value'),
            'editor.chip.snippet' => __('snippet'),
            'editor.code.area' => __('Snippet template'),
            ...$this->plurals->forms(
                'editor.code.fields',
                __('%1 field'),
                __('%1 fields')
            ),
            'editor.code.help' => __(
                'Written by hand, in the same syntax as the Code tab. The builder leaves it as it is.'
            ),
            'editor.code.hint' => __('Type {{ to pick a field'),
            'editor.code.label' => __('Template'),
            ...$this->plurals->forms(
                'editor.code.lines',
                __('%1 line'),
                __('%1 lines')
            ),
            'editor.code.listFooter' => __('↑ ↓ to move, Enter to insert'),
            'editor.code.listLabel' => __('Fields'),
            'editor.code.missingComma' => __(
                'Line %1: a comma is missing after the value on line %2. Save is off until the template is valid '
                . 'JSON; the preview shows the last valid version.'
            ),
            'editor.code.trailingComma' => __(
                'Line %1: the comma after the last value has nothing after it. Save is off until the template is '
                . 'valid JSON; the preview shows the last valid version.'
            ),
            'editor.code.unclosed' => __(
                'Line %1: the %2 opened here is never closed. Save is off until the template is valid JSON; the '
                . 'preview shows the last valid version.'
            ),
            'editor.code.unexpected' => __(
                'Line %1 has something that is not JSON. Save is off until the template is valid JSON; the '
                . 'preview shows the last valid version.'
            ),
            'editor.code.unfinished' => __('Line %1 is not finished. The preview shows the last valid version.'),
            'editor.code.valid' => __('Valid. %1, %2.'),
            'editor.drawer.add' => __('Add a property'),
            'editor.drawer.addButton' => __('Add property'),
            'editor.drawer.cancel' => __('Cancel'),
            'editor.drawer.done' => __('Done'),
            'editor.drawer.item' => __('Item %1'),
            'editor.drawer.name' => __('Name'),
            'editor.drawer.nameError' => __('Name the property as schema.org does.'),
            'editor.drawer.property' => __('Property'),
            'editor.drawer.remove' => __('Remove property'),
            'editor.drawer.suggestions' => __('Google recommends for %1'),
            'editor.drawer.suggestionsHelp' => __('Or type any other schema.org property below.'),
            'editor.drawer.value' => __('Value'),
            'editor.editAs.builder' => __('Builder'),
            'editor.editAs.code' => __('Code'),
            'editor.editAs.label' => __('Edit as'),
            'editor.field.empty' => __('(empty)'),
            'editor.field.failed' => __('The fields could not be loaded.'),
            'editor.field.help' => __('The right-hand column is what each field holds for %1.'),
            'editor.field.helpNoSample' => __(
                'The right-hand column is what each field holds for the page in the preview.'
            ),
            'editor.field.insteadLabel' => __('Value to use instead'),
            'editor.field.insteadPlaceholder' => __('Linden & Co.'),
            'editor.field.label' => __('Field'),
            'editor.field.leaveOut' => __('Leave the property out'),
            'editor.field.loading' => __('Loading the fields…'),
            'editor.field.none' => __(
                'No field or attribute matches. Every product attribute is listed, by label and by code.'
            ),
            'editor.field.search' => __('Search fields and attributes'),
            'editor.field.useInstead' => __('Use this value instead'),
            'editor.field.whenEmpty' => __('When the field is empty'),
            'editor.filter.limit' => __('first %1 characters'),
            'editor.filter.plain' => __('plain text'),
            'editor.fixed.enums' => __('Google accepts these for %1:'),
            'editor.fixed.label' => __('Text'),
            'editor.gives.empty' => __('Nothing: the value is empty for %1, so the property is left out.'),
            'editor.gives.emptyObject' => __('Nothing yet: an object with no properties is left out.'),
            'editor.gives.skipped' => __('Nothing: %1 has no value for %2, so the property is left out.'),
            'editor.gives.title' => __('For %1 this gives'),
            'editor.header.save' => __('Save'),
            'editor.header.switch' => __('Output this snippet'),
            'editor.header.test' => __('Test'),
            'editor.header.unsaved' => __('Unsaved changes'),
            'editor.include.failed' => __('The snippets could not be loaded.'),
            'editor.include.help' => __(
                'A snippet that already contains this one cannot be picked, so a loop cannot be built.'
            ),
            'editor.include.label' => __('Snippet'),
            'editor.include.loading' => __('Loading the snippets…'),
            'editor.include.loops' => __('Contains this snippet, so it cannot be used inside it.'),
            'editor.include.none' => __('There is no other snippet to use yet.'),
            'editor.kind.code' => __('Code'),
            'editor.kind.code.text' => __('A piece of template by hand'),
            'editor.kind.field' => __('From the store'),
            'editor.kind.field.text' => __('An attribute, a price, a setting'),
            'editor.kind.fixed' => __('Fixed value'),
            'editor.kind.fixed.text' => __('The same on every page'),
            'editor.kind.list' => __('List'),
            'editor.kind.list.text' => __('One item per image or review'),
            'editor.kind.object' => __('Object'),
            'editor.kind.object.text' => __('A thing with its own properties'),
            'editor.kind.snippet' => __('Another snippet'),
            'editor.kind.snippet.text' => __('Reuse a building block'),
            'editor.kind.text' => __('Text with fields'),
            'editor.kind.text.text' => __('A sentence that mixes both'),
            'editor.list.entered' => __('Items entered here'),
            'editor.list.helpItems' => __('Each item is typed in here and is the same on every page.'),
            'editor.list.helpStore' => __('One item is output for each of them, built as described on the rows below.'),
            'editor.list.items' => __('items'),
            'editor.list.max' => __('At most'),
            'editor.list.maxNote' => __('Empty means all of them.'),
            'editor.list.of' => __('One item for each'),
            'editor.object.help' => __('Its properties are edited in the list: use the plus on its row.'),
            'editor.object.placeholder' => __('Offer'),
            'editor.object.type' => __('Type'),
            'editor.pages.allStores' => __('All store views'),
            'editor.pages.builderFailed' => __(
                'The conditions could not be shown. Reload the page to try again; the placement is kept as it '
                . 'is.'
            ),
            'editor.pages.builderLoading' => __('Loading the conditions…'),
            'editor.pages.countFailed' => __('The pages could not be counted. The placement is kept as it is.'),
            'editor.pages.counting' => __('Counting the pages…'),
            'editor.pages.noKind' => __('Pick at least one kind of page, or the snippet is output nowhere.'),
            'editor.pages.nothingToNarrow' => __('Nothing to narrow down on %1.'),
            'editor.pages.onlyWhen' => __('Only when'),
            'editor.pages.optional' => __('Optional'),
            'editor.pages.outputOn' => __('Output on'),
            'editor.pages.pickKind' => __('Pick a kind of page first.'),
            'editor.pages.stores' => __('Store views'),
            'editor.pages.subtitle' => __('Where the snippet is output.'),
            'editor.pages.title' => __('Pages'),
            'editor.preview.copied' => __('Copied'),
            'editor.preview.copy' => __('Copy'),
            'editor.preview.failed' => __('The preview could not be made.'),
            'editor.preview.loading' => __('Making the preview…'),
            'editor.preview.meta' => __('%1 · %2'),
            'editor.preview.metaBlock' => __('Inside %1 · %2'),
            'editor.preview.noPage.text' => __(
                'Pick a kind of page in Pages: the preview shows the output for one of its pages.'
            ),
            'editor.preview.noPage.title' => __('No page to show it on'),
            'editor.preview.noteBlock' => __('As it appears inside its parent'),
            'editor.preview.noteLastValid' => __('Last valid version'),
            'editor.preview.notePage' => $this->outputLine->previewNote(),
            'editor.preview.output' => __('Output'),
            'editor.preview.retry' => __('Try again'),
            'editor.preview.sample' => __('Preview with'),
            'editor.preview.title' => __('Preview'),
            'editor.properties.add' => __('Add a property'),
            'editor.properties.empty.text' => __(
                'A snippet with no properties is not output. Add the first one, or switch to Code and paste a '
                . 'template.'
            ),
            'editor.properties.empty.title' => __('No properties yet'),
            'editor.properties.invalid' => __(
                'The template is not valid JSON, so the builder cannot draw it. Correct it in the Code tab.'
            ),
            'editor.properties.legend' => __(
                'A property whose value is empty is left out of the output. “Empty here” and “skipped here” '
                . 'are about the page in the preview: %1.'
            ),
            'editor.properties.legendNoSample' => __(
                'A property whose value is empty is left out of the output. “Empty here” and “skipped here” '
                . 'are about the page in the preview.'
            ),
            'editor.properties.opaque' => __(
                'The builder cannot draw this template: its top level is not a plain object. Change it in the '
                . 'Code tab.'
            ),
            'editor.properties.start.library' => __('Or start from a template in the library'),
            'editor.properties.start.text' => __(
                'What does the snippet describe? Type any schema.org type above, or pick one of these.'
            ),
            'editor.properties.start.title' => __('Start with a type'),
            'editor.properties.subtitle' => __(
                'What the snippet says. Click a row to change where its value comes from.'
            ),
            'editor.properties.subtitleCode' => __(
                'The same snippet as a template. What the builder cannot draw stays here as code.'
            ),
            'editor.properties.title' => __('Properties'),
            ...$this->plurals->forms(
                'editor.root.block',
                __('Building block · %1 property'),
                __('Building block · %1 properties')
            ),
            'editor.root.schema' => __('schema.org/%1'),
            ...$this->plurals->forms(
                'editor.root.snippet',
                __('Snippet · %1 property'),
                __('Snippet · %1 properties')
            ),
            'editor.row.addItem' => __('Add an item to %1'),
            'editor.row.addTo' => __('Add a property to %1'),
            'editor.row.addToItem' => __('Add a property to item %1'),
            'editor.row.collapse' => __('Collapse %1'),
            'editor.row.empty' => __('empty here'),
            'editor.row.expand' => __('Expand %1'),
            'editor.row.item' => __('Item %1'),
            'editor.row.new' => __('New property'),
            'editor.row.skipped' => __('skipped here'),
            'editor.row.when' => __('when %1'),
            'editor.save.block' => __('The snippets that use it output the new version from the next page view.'),
            'editor.save.failed' => __(
                'The snippet could not be saved: the server did not answer. Your changes are still here, and the '
                . 'storefront keeps the last saved version.'
            ),
            'editor.save.live' => __('The pages it is on output the new version from the next page view.'),
            'editor.save.off' => __('It is switched off, so nothing is output yet.'),
            'editor.save.refused' => __('The snippet could not be saved: %1'),
            'editor.save.reload' => __('Reload'),
            'editor.save.retry' => __('Try again'),
            'editor.save.saved' => __('%1 was saved.'),
            'editor.save.snippet' => __('The snippet'),
            'editor.snippet.block' => __(
                'A building block. It has no page of its own: it is output inside the snippets that use it, '
                . 'wherever they are output.'
            ),
            'editor.snippet.name' => __('Name'),
            'editor.snippet.nameHelp' => __('Shown in the admin only.'),
            'editor.snippet.namePlaceholder' => __('Product'),
            'editor.snippet.subtitle' => __('What it describes. Where it is output is set in Pages.'),
            'editor.snippet.subtitleBlock' => __('What it describes, and the snippets it is used in.'),
            'editor.snippet.title' => __('Snippet'),
            'editor.snippet.type' => __('Type'),
            'editor.snippet.typeError' => __('Enter a schema.org type, for example Product.'),
            'editor.snippet.typeHelp' => __('Any schema.org type, written as on schema.org.'),
            'editor.snippet.typePlaceholder' => __('Product'),
            'editor.snippet.usedIn' => __('Used in'),
            'editor.snippet.usedInNone' => __('No snippet uses it yet.'),
            'editor.switch.off' => __('Off'),
            'editor.switch.on' => __('On'),
            'editor.tabs.label' => __('Snippet'),
            'editor.tabs.pages' => __('Pages'),
            'editor.tabs.properties' => __('Properties'),
            'editor.text.help' => __('Left out when one of its fields is empty.'),
            'editor.text.insert' => __('Insert a field'),
            'editor.text.label' => __('Text'),
            'editor.title.new' => __('New snippet'),
            'editor.when.field' => __('Field to test'),
            'editor.when.has' => __('has a value'),
            'editor.when.label' => __('Only output it when'),
            'editor.when.switch' => __('Only output it when a field has a value'),
            'page.loading' => __('Loading…'),
            'page.loadingLabel' => __('Loading'),
        ];

        return array_map(
            static fn (Phrase|string $phrase): string => $phrase instanceof Phrase ? $phrase->render() : $phrase,
            $phrases
        );
    }
}
