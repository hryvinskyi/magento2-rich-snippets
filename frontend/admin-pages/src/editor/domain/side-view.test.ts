/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the side column and the save message decide from the server's answers: the store view the editor reads in,
 * the sample previewed, the count on the Checks panel, the note under the output, the output as printed, and what a
 * saved version reaches.
 */
import assert from 'node:assert/strict';
import { it } from 'node:test';
import type { CheckRow, Draft, Sample } from './model.ts';
import { checkCount, chosenSample, editorStore, outputNote, outputText } from './preview-view.ts';
import { savedReach } from './save-view.ts';

const row = (tone: CheckRow['tone']): CheckRow => ({ tone, title: tone, text: '', action: null });
const sample = (ref: string): Sample => ({ ref, kind: 'product', id: 1, label: ref, url: '', path: '' });

it('reads in the first store view the snippet is output in, else the default one', () => {
    assert.equal(editorStore([0], [2, 3]), null);
    assert.equal(editorStore([], [2, 3]), null);
    assert.equal(editorStore([3, 2], [2, 3]), 3);
});

it('never reads in a store view the shop no longer has', () => {
    assert.equal(editorStore([7, 3], [2, 3]), 3);
    assert.equal(editorStore([7], [2, 3]), null);
});

it('previews the sample wanted while it is listed, else the first, else none', () => {
    const samples = [sample('product:1'), sample('product:2')];
    assert.equal(chosenSample(samples, 'product:2'), samples[1]);
    assert.equal(chosenSample(samples, 'product:9'), samples[0]);
    assert.equal(chosenSample(samples, null), samples[0]);
    assert.equal(chosenSample([], 'product:1'), null);
});

it('counts the requirements missing, else the warnings, else nothing', () => {
    assert.deepEqual(checkCount([row('ok'), row('bad'), row('warn'), row('bad')]), { tone: 'bad', count: 2 });
    assert.deepEqual(checkCount([row('note'), row('warn')]), { tone: 'warn', count: 1 });
    assert.equal(checkCount([row('ok'), row('note')]), null);
});

it('notes the last valid version while the code is not a template, else a block or a page\'s block', () => {
    assert.equal(outputNote(true, true), 'lastValid');
    assert.equal(outputNote(false, true), 'lastValid');
    assert.equal(outputNote(true, false), 'block');
    assert.equal(outputNote(false, false), 'page');
});

it('prints the output with two spaces per level, and nothing for no output', () => {
    const shown = { sample: sample('product:1'), state: 'output' as const, message: null, checks: [] };
    assert.equal(outputText({ ...shown, output: { '@type': 'Product', offers: { price: '1' } } }), '{\n  "@type": "Product",\n  "offers": {\n    "price": "1"\n  }\n}');
    assert.equal(outputText({ ...shown, output: null }), '');
    assert.equal(outputText(null), '');
});

it('says a saved block reaches the snippets using it, a snippet switched on its pages, one switched off nowhere', () => {
    const draft: Draft = {
        id: 1, code: 'a', name: 'A', type: 'Thing', template: '{}', isBlock: false, active: true,
        pageKinds: [], conditions: '', storeIds: [0], origin: null, updatedAt: null, updatedBy: '',
    };
    assert.equal(savedReach({ ...draft, isBlock: true, active: false }), 'block');
    assert.equal(savedReach(draft), 'live');
    assert.equal(savedReach({ ...draft, active: false }), 'off');
});
