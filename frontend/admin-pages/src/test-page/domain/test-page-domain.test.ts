/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's pure decisions, without a DOM: the colours of a JSON text, how a page is named and where it is,
 * the bar's line, sizes and times, the looks, the address, and the catalog's body, run end and links.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { findingsBody, isChecking, itemLink, runEnded } from './catalog-view.ts';
import { jsonTokens } from './json-tokens.ts';
import type { CheckStatus, FindingItem, PickedPage, TestedBlock } from './model.ts';
import {
    addressParams,
    conditionsLink,
    kilobytes,
    nothingText,
    outputSummary,
    pickerName,
    runLine,
    samePage,
    searchOptions,
    seconds,
    settingsLink,
    whereOf,
} from './page-view.ts';

const satchel: PickedPage = { ref: 'product:12', kind: 'product', label: 'Field satchel', path: '/bags/field-satchel.html' };
const cart: PickedPage = { ref: null, kind: 'other', label: '/checkout/cart', path: '/checkout/cart' };
const home: PickedPage = { ref: 'home:2', kind: 'home', label: 'Home page', path: '/' };

const status = (state: CheckStatus['state'], change: Partial<CheckStatus> = {}): CheckStatus => ({
    state,
    tone: state === 'idle' ? 'ok' : 'busy',
    title: 't',
    note: 'n',
    facts: [],
    progress: null,
    last: { id: 7, toast: 'The catalog was checked.' },
    failed: null,
    ...change,
});

const block = (size: number, verdict: TestedBlock['verdict']): TestedBlock => ({
    code: 'product', snippetId: 3, name: 'Product', type: 'Product', json: '{}', size, lines: 1, verdict, summary: '',
});

describe('the colours of a JSON text', () => {
    it('tells keys from strings, numbers and punctuation, and leaves the words true, false and null plain', () => {
        const text = '{"name": "Satchel", "price": 24.5, "ok": true}';
        const kinds = jsonTokens(text).map((token) => [token.kind, text.slice(token.from, token.to)]);

        assert.deepEqual(kinds, [
            ['punctuation', '{'], ['key', '"name"'], ['punctuation', ':'], ['string', '"Satchel"'], ['punctuation', ','],
            ['key', '"price"'], ['punctuation', ':'], ['number', '24.5'], ['punctuation', ','],
            ['key', '"ok"'], ['punctuation', ':'], ['punctuation', '}'],
        ]);
    });

    it('keeps an escaped quote inside its string and colours a broken text as far as its pieces go', () => {
        const text = '{"a": "say \\"hi\\"", "b":';
        const strings = jsonTokens(text).filter((token) => token.kind === 'string').map((token) => text.slice(token.from, token.to));

        assert.deepEqual(strings, ['"say \\"hi\\""']);
    });
});

describe('how a page is named and where it is', () => {
    it('names a page by its kind, and a kind with no name by its label alone', () => {
        assert.deepEqual(pickerName(satchel), { key: 'test.picker.kind.product', params: ['Field satchel'] });
        assert.deepEqual(pickerName(cart), { key: 'test.picker.kind.other', params: ['/checkout/cart'] });
        assert.equal(pickerName(home), null);
    });

    it('says where a page is by its path, and by its label until a test has read its path', () => {
        assert.equal(whereOf(satchel), '/bags/field-satchel.html');
        assert.equal(whereOf({ ...satchel, path: '' }), 'Field satchel');
    });

    it('offers the pages found first and the text as any address after them', () => {
        assert.deepEqual(searchOptions({ pages: [satchel], address: '/checkout/cart' }), [satchel, cart]);
        assert.deepEqual(searchOptions({ pages: [satchel], address: null }), [satchel]);
    });

    it('tells the same page by its reference, and an address by its path', () => {
        assert.ok(samePage(satchel, { ...satchel, path: '' }));
        assert.ok(samePage(cart, { ...cart, label: 'x' }));
        assert.ok(!samePage(satchel, cart));
    });
});

describe('the bar, the subtitle and the empty sentence', () => {
    it('words what is read, how long the test took, and why it gave nothing', () => {
        assert.deepEqual(runLine('busy', '/bags', 0), { key: 'test.bar.line.busy', params: ['/bags'] });
        assert.deepEqual(runLine('done', '/bags', 412), { key: 'test.bar.line.done', params: [0.4] });
        assert.equal(runLine('failed', '', 0).key, 'test.bar.line.failed');
        assert.equal(runLine('error', '', 0).key, 'test.bar.line.error');
        assert.equal(runLine('none', '', 0).key, 'test.bar.line.none');
    });

    it('rounds sizes to a tenth of a kilobyte and times to a tenth of a second, never below a tenth', () => {
        assert.equal(kilobytes(1125 + 356), 1.4);
        assert.equal(seconds(20), 0.1);
        assert.equal(seconds(1260), 1.3);
    });

    it('counts the blocks, their size together and those with a problem', () => {
        assert.deepEqual(outputSummary([block(718, 'bad'), block(20, 'bad'), block(400, 'ok')]), { count: 3, kilobytes: 1.1, problems: 2 });
    });

    it('says nothing is set for an address, for a page, or that no snippet is set up for it', () => {
        assert.equal(nothingText('other', 3).key, 'test.nothing.address');
        assert.equal(nothingText('product', 3).key, 'test.nothing.page');
        assert.equal(nothingText('product', 0).key, 'test.nothing.none');
    });
});

describe('links and the address', () => {
    it('opens the editor on its conditions, and the settings on the module\'s section', () => {
        assert.deepEqual([...conditionsLink(4).params], [['id', '4'], ['section', 'pages']]);
        assert.deepEqual([...settingsLink().params], [['section', 'hryvinskyi_rich_snippets']]);
    });

    it('writes the page tested with its store view, an address by its path, or the catalog with its store view', () => {
        assert.deepEqual([...addressParams('page', satchel, 2, null)], [['page', 'product:12'], ['store', '2']]);
        assert.deepEqual([...addressParams('page', cart, 1, null)], [['path', '/checkout/cart'], ['store', '1']]);
        assert.deepEqual([...addressParams('page', null, 1, null)], [['store', '1']]);
        assert.deepEqual([...addressParams('catalog', satchel, 1, 3)], [['mode', 'catalog'], ['store', '3']]);
        assert.deepEqual([...addressParams('catalog', satchel, 1, null)], [['mode', 'catalog']]);
    });
});

describe('the catalog', () => {
    const table = { store: null, subtitle: '', findings: [] };

    it('is checking while a check is asked for or running', () => {
        assert.ok(isChecking(status('requested')));
        assert.ok(isChecking(status('running')));
        assert.ok(!isChecking(status('idle')));
    });

    it('shows a check on its way, else the findings, else that the last check found nothing, else that none ran', () => {
        const one = { ...table, findings: [{ key: 'k' } as never] };
        assert.equal(findingsBody(status('running'), one), 'checking');
        assert.equal(findingsBody(status('idle'), one), 'rows');
        assert.equal(findingsBody(status('idle'), table), 'clean');
        assert.equal(findingsBody(status('idle', { last: null }), table), 'none');
    });

    it('says a watched check ended with the server\'s words, or that it stopped, and nothing otherwise', () => {
        assert.deepEqual(runEnded(status('running'), status('idle')), { tone: 'success', text: 'The catalog was checked.' });
        assert.deepEqual(runEnded(status('running'), status('idle', { failed: 'It stopped.' })), { tone: 'error', text: 'It stopped.' });
        assert.equal(runEnded(status('idle'), status('idle')), null);
        assert.equal(runEnded(status('requested'), status('running')), null);
    });

    it('opens a product or a category in its store view, a CMS page on its own, and nothing for another kind', () => {
        const item = (kind: string, id: number | null): FindingItem =>
            ({ ref: `${kind}:1`, kind, id, store: 2, label: 'x', sku: null, group: null });
        assert.deepEqual(itemLink(item('product', 31)), { route: 'catalog/product/edit', params: new Map([['id', '31'], ['store', '2']]) });
        assert.deepEqual(itemLink(item('cms', 5)), { route: 'cms/page/edit', params: new Map([['page_id', '5']]) });
        assert.equal(itemLink(item('home', 2)), null);
        assert.equal(itemLink(item('product', null)), null);
    });
});
