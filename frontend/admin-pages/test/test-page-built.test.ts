/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The built Test page, run as the page runs it (see `built-page.ts`): its output started on the printed island root
 * with a document of the contract in the "Whole catalog" mode, its components reading the built kit, and a broken
 * document shown as the page's failure line.
 */
import assert from 'node:assert/strict';
import { it, mock } from 'node:test';
import { waitFor } from '@testing-library/preact';
import { builtPage } from './built-page.ts';
import { testPhrases } from '../src/test/test-page-harness.tsx';
import { testData, testDocument } from '../src/test/test-page-fixtures.ts';

const ISLAND = 'rich-snippets-test';
const OUTPUT = 'test.js';

const page = builtPage({ island: ISLAND, output: OUTPUT });

it('the built entry starts the Test page on the printed root, its components reading the built kit', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const root = page.place(testDocument(testData('catalog', { finding: null }), testPhrases()));

    await import(page.entry());

    await waitFor(() => assert.ok(root.querySelector('.rs-test__status')));
    assert.ok(root.querySelector(`[data-role="${ISLAND}-loading"]`) === null, 'the loading line is gone');
    assert.ok((root.textContent ?? '').includes('test.findings.title'), 'the page\'s wording from its document');
    assert.equal(root.querySelectorAll('.rs-test__finding').length, 4);
    assert.ok(root.querySelector('.hui-segmented'), 'the kit\'s mode switch, drawn by the built kit');
    await waitFor(() => assert.match(window.location.search, /mode=catalog/, 'the mode is written into the address'));
    assert.deepEqual(errors.mock.calls.map((call) => call.arguments.map(String).join(' ')), []);
    errors.mock.restore();
});

it('a broken document shows the page\'s failure line in place of the loading line', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const broken = testDocument(testData('catalog'), testPhrases());
    const root = page.place({ ...broken, urls: {} });
    const failed = root.getAttribute('data-failed-text') ?? '';

    await import(page.entry('again'));

    await waitFor(() => assert.ok(failed !== '' && (root.textContent ?? '').includes(failed)));
    assert.ok(root.querySelector('.rs-test__status') === null, 'nothing half-read reaches the screen');
    assert.ok(errors.mock.calls.length > 0, 'the fault is logged');
    errors.mock.restore();
});
