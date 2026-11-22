/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How the Test page's two modes change, event by event, without a DOM: the page test, and the catalog with its
 * polling counter, its message when a watched check ends, its drawer and its download.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { parseCheckStatus, parseFindingItems, parseFindingsTable, parsePageTest } from '../adapters/test-data.ts';
import { contractFindings, contractItems, contractRun, contractStatus } from '../../test/test-page-fixtures.ts';
import { initialCatalog, reduceCatalog } from './catalog-state.ts';
import { initialPageTest, reducePageTest, runLineOf } from './page-test-state.ts';

const failure = { kind: 'network', reason: null } as const;

describe('the page test', () => {
    const product = { ref: 'product:12', kind: 'product', label: 'Field satchel', path: '' };

    it('starts busy on a pick, takes the path the test read, and opens the first block of each answer', () => {
        let state = reducePageTest(initialPageTest(null, 1), { type: 'picked', page: product });
        assert.equal(runLineOf(state), 'busy');
        state = reducePageTest(state, { type: 'blockToggled', index: 1 });
        state = reducePageTest(state, { type: 'answered', test: parsePageTest(contractRun('done'), '$') });

        assert.equal(state.page?.path, '/bags/field-satchel.html');
        assert.deepEqual([...state.opened], [0]);
        assert.equal(runLineOf(state), 'done');
    });

    it('says nothing is picked, that the storefront did not answer, and that the test could not be run', () => {
        assert.equal(runLineOf(initialPageTest(null, 1)), 'none');
        assert.equal(runLineOf(initialPageTest(product, 1)), null);
        const unread = reducePageTest(initialPageTest(product, 1), { type: 'answered', test: parsePageTest(contractRun('failed'), '$') });
        assert.equal(runLineOf(unread), 'failed');
        assert.equal(runLineOf(reducePageTest(unread, { type: 'failed', failure })), 'error');
    });

    it('toggles a block open and closed, and moves to another store view busy', () => {
        let state = reducePageTest(initialPageTest(product, 1), { type: 'blockToggled', index: 2 });
        assert.deepEqual([...state.opened].sort(), [0, 2]);
        state = reducePageTest(state, { type: 'blockToggled', index: 0 });
        assert.deepEqual([...state.opened], [2]);
        state = reducePageTest(state, { type: 'storeChosen', store: 3 });
        assert.deepEqual([state.store, state.run.status], [3, 'busy']);
    });
});

describe('the catalog', () => {
    const idle = parseCheckStatus(contractStatus('idle'), '$');
    const running = parseCheckStatus(contractStatus('running'), '$');
    const table = parseFindingsTable(contractFindings('findings'), '$');

    it('counts each answer about where the check stands, and says so when a watched check ends', () => {
        let state = reduceCatalog(initialCatalog(running, table), { type: 'statusLoaded', status: running });
        assert.deepEqual([state.polls, state.toast], [1, null]);
        state = reduceCatalog(state, { type: 'statusLoaded', status: idle });

        assert.equal(state.polls, 2);
        assert.deepEqual(state.toast, { kind: 'ended', tone: 'success', text: 'The catalog was checked: 207 pages in 3 store views.' });
        assert.equal(state.findings.status, 'loading');
    });

    it('asks again after a failure it may retry, and stops watching with a message after one it may not', () => {
        const state = initialCatalog(running, table);
        assert.equal(reduceCatalog(state, { type: 'statusFailed', failure, retry: true }).polls, 1);
        const stopped = reduceCatalog(state, { type: 'statusFailed', failure: { kind: 'expired', reason: null }, retry: false });
        assert.equal(stopped.watching, false);
        assert.equal(stopped.toast?.kind, 'failed');
    });

    it('drops the pages of a drawer no longer open, and closes a drawer whose finding is gone', () => {
        let state = reduceCatalog(initialCatalog(idle, table), { type: 'drawerOpened', key: 'merchant-listing-image' });
        state = reduceCatalog(state, { type: 'itemsLoaded', key: 'other', items: parseFindingItems(contractItems('first'), '$') });
        assert.equal(state.drawer?.items.status, 'loading');
        state = reduceCatalog(state, { type: 'findingsLoaded', table: parseFindingsTable(contractFindings('clean'), '$') });
        assert.equal(state.drawer, null);
    });

    it('keeps the drawer open over a download, with its failure inside it or the server\'s words above the page', () => {
        let state = reduceCatalog(initialCatalog(idle, table), { type: 'drawerOpened', key: 'merchant-listing-image' });
        state = reduceCatalog(state, { type: 'downloadStarted' });
        const failed = reduceCatalog(state, { type: 'downloadFailed', key: 'merchant-listing-image', failure });
        assert.deepEqual([failed.drawer?.downloading, failed.drawer?.downloadFailure], [false, failure]);
        const saved = reduceCatalog(state, { type: 'downloadDone', key: 'merchant-listing-image', text: '2 products were saved.' });
        assert.deepEqual([saved.drawer?.downloading, saved.toast], [false, { kind: 'saved', text: '2 products were saved.' }]);
    });
});
