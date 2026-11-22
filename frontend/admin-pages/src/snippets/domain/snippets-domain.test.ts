/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's pure rules, on the contract's list: which rows a chip lets through and how many it counts, what
 * a row says about where it is output and what it holds, how it is drawn, what deleting asks, what the card holds, and
 * what an import would do to each snippet of the file.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { phrase } from '@hryvinskyi/admin-ui';
import { parseImportPreview, parseListing } from '../adapters/snippets-data.ts';
import { contractList, emptyList, importPreview, KINDS, listWith } from '../../test/snippets-fixtures.ts';
import { ALL, chipsOf, filterKey, noMatchOf, rowsOf, type Filter } from './filters.ts';
import {
    defaultChoices,
    effectLine,
    fileMeta,
    fileRefusalOf,
    fileSize,
    importedCount,
    problemOf,
    runLabel,
    statusLine,
} from './import-view.ts';
import { cardBodyOf, partsOf } from './list-view.ts';
import type { ImportEntry, SnippetRow } from './model.ts';
import { deleteConfirmOf, refusalOf } from './refusals.ts';
import { metaLine, placementLine, rowLook, subtitleOf } from './row-view.ts';
import type { Days, Words } from './wording.ts';

const kinds = new Map(KINDS.map((kind) => [kind.code, kind]));

/** Words that show each key with its values, so a test reads which phrase was chosen and what filled it. */
const words: Words = {
    t: (key, ...params) => [key, ...params.map(String)].join(' | '),
    tn: (key, count, ...params) => [`${key}#${String(count)}`, ...params.map(String)].join(' | '),
    n: (value) => String(value),
    list: (values) => values.join(', '),
};

const days: Days = {
    short: (at) => `short(${at})`,
    long: (at) => `long(${at})`,
    full: (at) => `full(${at})`,
};

const listing = () => parseListing(contractList(), '$');

function rowOf(code: string): SnippetRow {
    const row = listing().rows.find((candidate) => candidate.code === code);
    assert.ok(row, `the contract has the row ${code}`);
    return row;
}

const codes = (rows: readonly SnippetRow[]): readonly string[] => rows.map((row) => row.code);

describe('chips', () => {
    it('let through every row, the rows that need attention, the snippets of a kind of page, or the building blocks', () => {
        const { rows } = listing();
        const through = (filter: Filter): readonly string[] => codes(rowsOf(rows, filter));

        assert.equal(through(ALL).length, 6);
        assert.deepEqual(through({ kind: 'attention' }), ['product', 'product-imported']);
        assert.deepEqual(through({ kind: 'page', page: 'product' }), ['product', 'product-imported']);
        assert.deepEqual(through({ kind: 'page', page: 'other' }), []);
        assert.deepEqual(through({ kind: 'blocks' }), ['return-policy']);
    });

    it('count from the server: every snippet, those needing attention, each kind of page in its order, the blocks', () => {
        const chips = chipsOf(listing().counts, KINDS);

        assert.deepEqual(chips.map((chip) => [chip.key, chip.count, chip.tone]), [
            ['all', 6, 'neutral'],
            ['attention', 2, 'warning'],
            ['page:home', 1, 'neutral'],
            ['page:product', 2, 'neutral'],
            ['page:category', 1, 'neutral'],
            ['page:cms', 1, 'neutral'],
            ['page:other', 0, 'neutral'],
            ['blocks', 1, 'neutral'],
        ]);
        assert.deepEqual(chips[0]?.label, phrase('snippets.chips.all'));
        assert.equal(chips[2]?.label, 'Home page');
    });

    it('leave out the attention chip while nothing needs attention, and count a kind the server left out as none', () => {
        const calm = parseListing(listWith((row) => row['code'] === 'organization'), '$');
        const chips = chipsOf(calm.counts, KINDS);

        assert.equal(chips.some((chip) => chip.key === 'attention'), false);
        assert.equal(chips.find((chip) => chip.key === 'page:cms')?.count, 0);
    });

    it('are told apart by their key', () => {
        assert.equal(filterKey(ALL), 'all');
        assert.equal(filterKey({ kind: 'page', page: 'cms' }), 'page:cms');
    });

    it('say what is not there for the chip and the store view picked', () => {
        assert.deepEqual(noMatchOf(ALL, kinds, 'Deutsch'), phrase('snippets.noMatch.store', 'Deutsch'));
        assert.deepEqual(noMatchOf({ kind: 'attention' }, kinds, null), phrase('snippets.noMatch.attention'));
        assert.deepEqual(noMatchOf({ kind: 'blocks' }, kinds, null), phrase('snippets.noMatch.blocks'));
        assert.deepEqual(noMatchOf({ kind: 'page', page: 'other' }, kinds, null), phrase('snippets.noMatch.kind', 'every other page'));
        assert.deepEqual(
            noMatchOf({ kind: 'page', page: 'cms' }, kinds, 'Deutsch'),
            phrase('snippets.noMatch.kindIn', 'CMS pages', 'Deutsch'),
        );
    });
});

describe('a row', () => {
    it('says where a page snippet is output: its kinds of page, the condition the server summed up, the store views', () => {
        assert.equal(
            placementLine(rowOf('organization'), kinds, words),
            'The home page · snippets.row.allViews',
        );
        assert.equal(
            placementLine(rowOf('product'), kinds, words),
            'snippets.row.where | Product pages | Product type is not Configurable · snippets.row.allViews',
        );
        assert.equal(
            placementLine(rowOf('faq'), kinds, words),
            'snippets.row.where | CMS pages | CMS page is one of FAQ, Shipping & returns · snippets.row.someViews | English',
        );
    });

    it('never says every store view for a snippet set for no store view the shop still has', () => {
        const gone = { ...rowOf('faq'), storeIds: [7], storeNames: [] };
        assert.equal(
            placementLine(gone, kinds, words),
            'snippets.row.where | CMS pages | CMS page is one of FAQ, Shipping & returns · snippets.row.noViews',
        );
        assert.equal(placementLine({ ...gone, storeIds: [] }, kinds, words).endsWith(' · snippets.row.noViews'), true);
    });

    it('says which snippets a building block is written inside, or that none uses it', () => {
        assert.equal(placementLine(rowOf('return-policy'), kinds, words), 'snippets.blocks.inside | Organization, Product');
        assert.equal(placementLine({ ...rowOf('return-policy'), usedIn: [] }, kinds, words), 'snippets.blocks.unused');
    });

    it('says a page snippet set for no kind of page is output nowhere', () => {
        assert.equal(placementLine({ ...rowOf('organization'), pageKinds: [] }, kinds, words), 'snippets.row.noKinds · snippets.row.allViews');
    });

    it('says how many properties it has, the blocks it uses (by code when the block is gone) and when it changed', () => {
        assert.equal(
            metaLine(rowOf('organization'), words, days),
            'snippets.row.properties#14 · snippets.row.uses | Return policy, Postal address'
                + ' · snippets.row.changed | short(2026-11-03 09:12:00) | admin',
        );
        assert.equal(
            metaLine(rowOf('product-imported'), words, days),
            'snippets.row.properties#19 · snippets.row.uses | gone-block · snippets.row.changed | short(2026-11-13 10:05:00) | admin',
        );
        assert.equal(
            metaLine({ ...rowOf('product-list'), updatedBy: '' }, words, days),
            'snippets.row.properties#5 · snippets.row.changedAt | short(2026-10-14 08:00:00)',
        );
        assert.equal(metaLine({ ...rowOf('product-list'), updatedAt: null }, words, days), 'snippets.row.properties#5');
    });

    it('is drawn with its kind\'s glyph, an edge for a bad or a warning flag, and muted while a page snippet is off', () => {
        assert.deepEqual(rowLook(rowOf('product'), true, kinds), { icon: 'inventory_2', edge: 'bad', muted: false });
        assert.deepEqual(rowLook(rowOf('product-imported'), true, kinds), { icon: 'inventory_2', edge: 'warn', muted: false });
        assert.deepEqual(rowLook(rowOf('product-list'), false, kinds), { icon: 'category', edge: 'none', muted: true });
        assert.deepEqual(rowLook(rowOf('return-policy'), false, kinds), { icon: 'widgets', edge: 'none', muted: false });
        assert.equal(rowLook({ ...rowOf('organization'), pageKinds: ['unknown'] }, true, kinds).icon, 'web');
    });

    it('counts the card\'s snippets that are on and off and its building blocks', () => {
        const counts = listing().counts;

        assert.equal(subtitleOf(counts, words), 'snippets.card.subtitleOff | 4 | 1 | snippets.card.blocks#1');
        assert.equal(subtitleOf({ ...counts, off: 0 }, words), 'snippets.card.subtitle | 4 | snippets.card.blocks#1');
        assert.equal(subtitleOf(parseListing(emptyList(), '$').counts, words), 'snippets.card.empty');
    });
});

describe('deleting', () => {
    it('asks whether to delete, saying more for a snippet that is on', () => {
        assert.deepEqual(deleteConfirmOf(rowOf('organization'), null), { kind: 'ask', question: phrase('snippets.delete.askOn', 'Organization') });
        assert.deepEqual(deleteConfirmOf(rowOf('product-list'), null), { kind: 'ask', question: phrase('snippets.delete.askOff', 'Product list') });
    });

    it('says a building block cannot go while snippets use it, naming them, or those the server named', () => {
        assert.deepEqual(deleteConfirmOf(rowOf('return-policy'), null), { kind: 'inUse', users: ['Organization', 'Product'] });
        assert.deepEqual(
            deleteConfirmOf({ ...rowOf('return-policy'), usedIn: [] }, [{ code: 'faq', name: null }]),
            { kind: 'inUse', users: ['faq'] },
        );
    });

    it('words a refusal by its reason, and one it does not know in general words', () => {
        assert.deepEqual(refusalOf('template_invalid'), phrase('snippets.refused.template_invalid'));
        assert.deepEqual(refusalOf('stores_missing'), phrase('snippets.refused.stores_missing'));
        assert.deepEqual(refusalOf('something_new'), phrase('snippets.refused.other'));
    });
});

describe('the card', () => {
    const shown = listing();

    it('holds grey rows while loading and the failure after it failed', () => {
        assert.deepEqual(cardBodyOf('loading', shown, ALL, null, kinds), { kind: 'loading' });
        assert.deepEqual(cardBodyOf('failed', shown, ALL, null, kinds), { kind: 'failed' });
    });

    it('holds the first-visit invitation only when the shop has no snippet at all', () => {
        const none = parseListing(emptyList(), '$');

        assert.deepEqual(cardBodyOf('ready', none, ALL, null, kinds), { kind: 'empty' });
        assert.deepEqual(cardBodyOf('ready', none, ALL, 'Deutsch', kinds), { kind: 'noMatch', text: phrase('snippets.noMatch.store', 'Deutsch') });
    });

    it('holds the rows the chip lets through, or what is not there', () => {
        const body = cardBodyOf('ready', shown, { kind: 'blocks' }, null, kinds);

        assert.equal(body.kind, 'rows');
        assert.deepEqual(body.kind === 'rows' ? codes(body.rows) : [], ['return-policy']);
        assert.deepEqual(
            cardBodyOf('ready', shown, { kind: 'page', page: 'other' }, null, kinds),
            { kind: 'noMatch', text: phrase('snippets.noMatch.kind', 'every other page') },
        );
    });

    it('shows chips and Export all for a list with snippets, the store bar while there are snippets or a view is picked', () => {
        const none = parseListing(emptyList(), '$');

        assert.deepEqual(partsOf('ready', shown, null), { chips: true, storeBar: true, exportAll: true, importFile: true });
        assert.deepEqual(partsOf('loading', shown, 2), { chips: false, storeBar: true, exportAll: false, importFile: false });
        assert.deepEqual(partsOf('ready', none, null), { chips: false, storeBar: false, exportAll: false, importFile: true });
        assert.deepEqual(partsOf('ready', none, 2), { chips: false, storeBar: true, exportAll: false, importFile: true });
    });
});

describe('an import', () => {
    const preview = parseImportPreview(importPreview(), '$');

    function entryOf(code: string): ImportEntry {
        const entry = preview.snippets.find((candidate) => candidate.code === code);
        assert.ok(entry, `the preview has ${code}`);
        return entry;
    }

    it('starts with the first choice the server offers each snippet, and counts the snippets it adds or replaces', () => {
        const choices = defaultChoices(preview);

        assert.deepEqual([...choices], [
            ['breadcrumbs', 'add'],
            ['product', 'replace'],
            ['organization', 'skip'],
            ['return-policy', 'replace'],
        ]);
        assert.equal(importedCount(choices), 3);
        assert.equal(runLabel(3, words), 'snippets.import.runCount#3');
        assert.equal(runLabel(0, words), 'snippets.import.run');
    });

    it('says how each snippet stands against the shop\'s', () => {
        assert.equal(statusLine(entryOf('breadcrumbs'), words, days), 'snippets.import.status.new');
        assert.equal(
            statusLine(entryOf('product'), words, days),
            'snippets.import.status.changed | long(2026-11-12 14:30:00) | long(2026-11-13 09:00:00)',
        );
        assert.equal(statusLine(entryOf('return-policy'), words, days), 'snippets.import.status.changedUndated');
        assert.equal(statusLine(entryOf('organization'), words, days), 'snippets.import.status.identical');
    });

    it('words each problem by its reason, with the values the server named', () => {
        assert.deepEqual(problemOf(entryOf('product').problems[0] ?? { reason: '', params: new Map() }), phrase('snippets.import.problem.store_unknown', 'trade_de'));
        assert.deepEqual(
            problemOf({ reason: 'condition_unresolved', params: new Map([['value', '42']]) }),
            phrase('snippets.import.problem.condition_unresolved', '42'),
        );
        assert.deepEqual(problemOf({ reason: 'brand_new', params: new Map() }), phrase('snippets.import.problem.other'));
    });

    it('says what each choice does: where an added snippet goes, whether a replaced one stays on, what is kept', () => {
        const breadcrumbs = entryOf('breadcrumbs');
        const product = entryOf('product');

        assert.equal(effectLine(breadcrumbs, 'add', null, kinds, words), 'snippets.import.effect.add | product pages, category pages');
        assert.equal(
            effectLine({ ...breadcrumbs, conditionSummary: 'Price is above 10' }, 'add', null, kinds, words),
            'snippets.import.effect.addWhere | product pages, category pages | Price is above 10',
        );
        assert.equal(effectLine({ ...breadcrumbs, pageKinds: [] }, 'add', null, kinds, words), 'snippets.import.effect.addNowhere');
        assert.equal(effectLine({ ...breadcrumbs, isBlock: true }, 'add', null, kinds, words), 'snippets.import.effect.addBlock');
        assert.equal(effectLine(breadcrumbs, 'skip', null, kinds, words), 'snippets.import.effect.skipNew');
        assert.equal(effectLine(product, 'replace', true, kinds, words), 'snippets.import.effect.replaceOn');
        assert.equal(effectLine(product, 'replace', false, kinds, words), 'snippets.import.effect.replaceOff');
        assert.equal(effectLine(entryOf('return-policy'), 'replace', false, kinds, words), 'snippets.import.effect.replaceBlock');
        assert.equal(effectLine(product, 'keep_both', true, kinds, words), 'snippets.import.effect.keepBoth | Product');
        assert.equal(effectLine(entryOf('return-policy'), 'keep_both', null, kinds, words), 'snippets.import.effect.keepBothBlock | Return policy');
        assert.equal(effectLine(product, 'skip', true, kinds, words), 'snippets.import.effect.skipExisting');
    });

    it('describes the file: how many snippets, when and where it was exported, or its size', () => {
        assert.equal(fileMeta(preview, words, days), 'snippets.import.file.count#4 · snippets.import.file.exportedFrom | full(2026-11-13 10:00:00) | shop.example');
        assert.equal(fileMeta({ ...preview, source: '' }, words, days), 'snippets.import.file.count#4 · snippets.import.file.exported | full(2026-11-13 10:00:00)');
        assert.equal(fileMeta({ ...preview, exportedAt: '' }, words, days), 'snippets.import.file.count#4');
        assert.equal(fileSize(10, words), 'snippets.import.file.kilobytes | 1');
        assert.equal(fileSize(5000, words), 'snippets.import.file.kilobytes | 5');
    });

    it('words why the server refused a file, and a refusal at the commit as a change in between', () => {
        assert.deepEqual(fileRefusalOf('not_an_export'), phrase('snippets.import.refused.not_an_export'));
        assert.deepEqual(fileRefusalOf('choice_not_offered'), phrase('snippets.import.refused.changed'));
        assert.equal(fileRefusalOf('store'), null);
        assert.equal(fileRefusalOf(null), null);
    });
});
