/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The built Overview, run as the page runs it (see `built-page.ts`): its output started on the printed island root
 * with a document of the contract, its components reading the built kit, and a broken document shown as the page's
 * failure line.
 */
import assert from 'node:assert/strict';
import { it, mock } from 'node:test';
import { waitFor } from '@testing-library/preact';
import { builtPage } from './built-page.ts';
import { contractPhrases } from '../src/test/harness.tsx';
import { pageDocument, stateData } from '../src/test/fixtures.ts';

const ISLAND = 'rich-snippets-overview';
const OUTPUT = 'overview.js';

const page = builtPage({ island: ISLAND, output: OUTPUT });

it('the built entry starts the Overview on the printed root, its components reading the built kit', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const root = page.place(pageDocument(stateData('attention'), contractPhrases()));

    await import(page.entry());

    await waitFor(() => assert.ok(root.querySelector('.rs-overview__status')));
    assert.equal(root.querySelector(`[data-role="${ISLAND}-loading"]`), null, 'the loading line is gone');
    assert.ok((root.textContent ?? '').includes('overview.kinds.title'), 'the page\'s wording from its document');
    assert.equal(root.querySelectorAll('.rs-overview__item').length, 4);
    assert.ok(root.querySelector('nav.hui-modnav a[aria-current="page"]'), 'the kit\'s navigation, drawn by the built kit');
    assert.ok(root.querySelector('select#rs-overview-store'), 'the kit\'s store-view picker');
    assert.deepEqual(errors.mock.calls.map((call) => call.arguments.map(String).join(' ')), []);
    errors.mock.restore();
});

it('a broken document shows the page\'s failure line in place of the loading line', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const broken = pageDocument(stateData('attention'), contractPhrases());
    const root = page.place({ ...broken, routes: [] });
    const failed = root.getAttribute('data-failed-text') ?? '';

    await import(page.entry('again'));

    await waitFor(() => assert.ok(failed !== '' && (root.textContent ?? '').includes(failed)));
    assert.equal(root.querySelector('.rs-overview__status'), null, 'nothing half-read reaches the screen');
    assert.ok(errors.mock.calls.length > 0, 'the fault is logged');
    errors.mock.restore();
});
