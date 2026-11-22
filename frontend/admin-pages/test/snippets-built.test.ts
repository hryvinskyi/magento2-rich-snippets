/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The built Snippets page, run as the page runs it (see `built-page.ts`): its output started on the printed island
 * root with a document of the contract, its components reading the built kit, and a broken document shown as the
 * page's failure line.
 */
import assert from 'node:assert/strict';
import { it, mock } from 'node:test';
import { waitFor } from '@testing-library/preact';
import { builtPage } from './built-page.ts';
import { snippetsPhrases } from '../src/test/snippets-harness.tsx';
import { snippetsData, snippetsDocument } from '../src/test/snippets-fixtures.ts';

const ISLAND = 'rich-snippets-snippets';
const OUTPUT = 'snippets.js';

const page = builtPage({ island: ISLAND, output: OUTPUT });

it('the built entry starts the Snippets page on the printed root, its components reading the built kit', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const root = page.place(snippetsDocument(snippetsData(), snippetsPhrases()));

    await import(page.entry());

    await waitFor(() => assert.ok(root.querySelector('.rs-snippets__rows')));
    assert.ok(root.querySelector(`[data-role="${ISLAND}-loading"]`) === null, 'the loading line is gone');
    assert.ok((root.textContent ?? '').includes('snippets.card.title'), 'the page\'s wording from its document');
    assert.equal(root.querySelectorAll('.rs-snippets__item').length, 6);
    assert.equal(root.querySelectorAll('[role="switch"]').length, 5, 'the kit\'s switches, drawn by the built kit');
    assert.ok(root.querySelector('.hui-menu__button'), 'the kit\'s row menu');
    assert.ok(root.querySelector('select#rs-snippets-store'), 'the kit\'s store-view picker');
    assert.deepEqual(errors.mock.calls.map((call) => call.arguments.map(String).join(' ')), []);
    errors.mock.restore();
});

it('a broken document shows the page\'s failure line in place of the loading line', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const broken = snippetsDocument(snippetsData(), snippetsPhrases());
    const root = page.place({ ...broken, urls: {} });
    const failed = root.getAttribute('data-failed-text') ?? '';

    await import(page.entry('again'));

    await waitFor(() => assert.ok(failed !== '' && (root.textContent ?? '').includes(failed)));
    assert.ok(root.querySelector('.rs-snippets__rows') === null, 'nothing half-read reaches the screen');
    assert.ok(errors.mock.calls.length > 0, 'the fault is logged');
    errors.mock.restore();
});
