/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The built snippet editor, run as the page runs it (see `built-page.ts`): its output started on the printed island
 * root with a document of the contract — the builder, the choice of the Code tab, the Preview and Checks panels, its
 * preview asked of the page's endpoint — its components reading the built kit, and a broken document shown as the
 * page's failure line.
 */
import assert from 'node:assert/strict';
import { it, mock } from 'node:test';
import { waitFor } from '@testing-library/preact';
import { builtPage } from './built-page.ts';
import { editorPhrases } from '../src/test/editor-harness.tsx';
import { editorData, editorDocument } from '../src/test/editor-fixtures.ts';

const ISLAND = 'rich-snippets-editor';
const OUTPUT = 'editor.js';

const page = builtPage({ island: ISLAND, output: OUTPUT });

it('the built entry starts the snippet editor on the printed root, its components reading the built kit', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const fetches = mock.method(globalThis, 'fetch', () => Promise.reject(new Error('offline')));
    const root = page.place(editorDocument(editorData(), editorPhrases()));

    await import(page.entry());

    await waitFor(() => assert.ok(root.querySelector('.rs-editor__rows')));
    assert.ok(root.querySelector(`[data-role="${ISLAND}-loading"]`) === null, 'the loading line is gone');
    assert.ok((root.textContent ?? '').includes('editor.properties.title'), 'the page\'s wording from its document');
    assert.equal(root.querySelectorAll('[role="tab"]').length, 2);
    assert.ok(root.querySelector('[role="switch"]'), 'the kit\'s switch, drawn by the built kit');
    assert.ok(root.querySelectorAll('.rs-editor__row').length > 0);
    assert.ok((root.textContent ?? '').includes('editor.editAs.code'), 'the choice of the Code tab');
    assert.ok(root.querySelector('.rs-editor__preview') !== null, 'the Preview panel');
    assert.ok(root.querySelector('.rs-editor__checks') !== null, 'the Checks panel');
    await waitFor(() => assert.ok(fetches.mock.calls.some((call) => String(call.arguments[0]).includes('/snippets/samples/'))));
    fetches.mock.restore();
    errors.mock.restore();
});

it('a broken document shows the page\'s failure line in place of the loading line', async () => {
    const errors = mock.method(console, 'error', () => undefined);
    const broken = editorDocument(editorData(), editorPhrases());
    const root = page.place({ ...broken, urls: {} });
    const failed = root.getAttribute('data-failed-text') ?? '';

    await import(page.entry('again'));

    await waitFor(() => assert.ok(failed !== '' && (root.textContent ?? '').includes(failed)));
    assert.ok(root.querySelector('.rs-editor__rows') === null, 'nothing half-read reaches the screen');
    assert.ok(errors.mock.calls.length > 0, 'the fault is logged');
    errors.mock.restore();
});
