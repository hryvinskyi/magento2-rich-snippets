/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The built Library page, run as the page runs it (see `built-page.ts`): its output started on the printed island
 * root with a document of the contract, its components reading the built kit, and a broken document shown as the
 * page's failure line.
 */
import assert from 'node:assert/strict';
import { it, mock } from 'node:test';
import { waitFor } from '@testing-library/preact';
import { builtPage } from './built-page.ts';
import { libraryPhrases } from '../src/test/library-harness.tsx';
import { contractLibrary, libraryData, libraryDocument } from '../src/test/library-fixtures.ts';

const ISLAND = 'rich-snippets-library';
const OUTPUT = 'library.js';

const page = builtPage({ island: ISLAND, output: OUTPUT });

it('the built entry starts the Library page on the printed root, its components reading the built kit', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const root = page.place(libraryDocument(libraryData(contractLibrary('first')), libraryPhrases()));

    await import(page.entry());

    await waitFor(() => assert.ok(root.querySelector('.rs-library__grid')));
    assert.ok(root.querySelector(`[data-role="${ISLAND}-loading"]`) === null, 'the loading line is gone');
    assert.ok((root.textContent ?? '').includes('library.card.title'), 'the page\'s wording from its document');
    assert.equal(root.querySelectorAll('.rs-library__template').length, 13);
    assert.equal(root.querySelectorAll('.rs-library__set-item').length, 5, 'the recommended set of a shop that has none');
    assert.ok(root.querySelector('.hui-chip'), 'the kit\'s chips, drawn by the built kit');
    assert.deepEqual(errors.mock.calls.map((call) => call.arguments.map(String).join(' ')), []);
    errors.mock.restore();
});

it('a broken document shows the page\'s failure line in place of the loading line', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const broken = libraryDocument(libraryData(), libraryPhrases());
    const root = page.place({ ...broken, urls: {} });
    const failed = root.getAttribute('data-failed-text') ?? '';

    await import(page.entry('again'));

    await waitFor(() => assert.ok(failed !== '' && (root.textContent ?? '').includes(failed)));
    assert.ok(root.querySelector('.rs-library__grid') === null, 'nothing half-read reaches the screen');
    assert.ok(errors.mock.calls.length > 0, 'the fault is logged');
    errors.mock.restore();
});
