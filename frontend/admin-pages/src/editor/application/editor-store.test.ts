/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The editor's store as the seam other parts of the editor use: draft changes with the type kept in step, a template
 * put in from code, saving (with changes made while the save was on their way kept), the preview, the type error and
 * the Code tab's invalid text, and the announcements of save, test and the Pages section.
 */
import assert from 'node:assert/strict';
import { it } from 'node:test';
import type { Draft } from '../domain/model.ts';
import { createEditorStore } from './editor-store.ts';

const SAVED: Draft = {
    id: 3, code: 'product', name: 'Product', type: 'Product', template: '{"@type": "Product"}', isBlock: false, active: true,
    pageKinds: ['product'], conditions: '', storeIds: [0], origin: null, updatedAt: null, updatedBy: '',
};

it('changes the draft, tells its listeners, and keeps the type in step with a template put in from code', () => {
    const store = createEditorStore(SAVED);
    let told = 0;
    const stop = store.subscribe(() => {
        told++;
    });
    store.change((draft) => ({ ...draft, name: 'Bag' }));
    store.change((draft) => draft);
    store.setTemplate('{"@type": "Offer"}');
    assert.equal(told, 2, 'a change that changes nothing tells no one');
    assert.equal(store.state().draft.name, 'Bag');
    assert.equal(store.state().draft.type, 'Offer');
    assert.equal(store.state().saved, SAVED);
    stop();
    store.change((draft) => ({ ...draft, name: 'Satchel' }));
    assert.equal(told, 2);
});

it('takes a saved draft as the snippet, clears the type error when the type changes, and holds the preview', () => {
    const store = createEditorStore(SAVED);
    store.showTypeError(true);
    store.change((draft) => ({ ...draft, name: 'Bag' }));
    assert.equal(store.state().typeError, true, 'a change elsewhere keeps the type marked');
    store.setTemplate('{"@type": "Offer"}');
    assert.equal(store.state().typeError, false);
    const sample = { ref: 'product:12', kind: 'product', id: 12, label: 'Field satchel', url: 'https://shop.test/a', path: '/a' };
    store.showPreview({ sample, state: 'output', output: { name: 'Bag' }, message: null, checks: [] });
    assert.equal(store.state().preview?.sample.label, 'Field satchel');
    const saved = { ...store.state().draft, updatedAt: '2026-11-22 10:00:00' };
    store.markSaved(saved);
    assert.equal(store.state().saved, saved);
    assert.equal(store.state().draft, saved);
});

it('keeps the changes made while a save was on its way, on the saved snippet\'s identity and version', () => {
    const store = createEditorStore({ ...SAVED, id: null, code: '' });
    const sent = store.state().draft;
    store.change((draft) => ({ ...draft, name: 'Changed meanwhile' }));
    const stored = { ...sent, id: 7, code: 'product-2', updatedAt: '2026-11-22 10:00:00', updatedBy: 'admin' };
    store.markSaved(stored, sent);
    assert.equal(store.state().saved, stored);
    assert.deepEqual(store.state().draft, { ...stored, name: 'Changed meanwhile' });
    const unchanged = store.state().draft;
    const again = { ...unchanged, updatedAt: '2026-11-22 10:05:00' };
    store.markSaved(again, unchanged);
    assert.equal(store.state().draft, again);
});

it('marks the Code tab\'s text as not a template and tells only of a change', () => {
    const store = createEditorStore(SAVED);
    let told = 0;
    store.subscribe(() => {
        told++;
    });
    store.showCodeInvalid(true);
    store.showCodeInvalid(true);
    assert.equal(store.state().codeInvalid, true);
    store.showCodeInvalid(false);
    assert.equal(told, 2);
});

it('announces save, test and the Pages section to their own listeners only', () => {
    const store = createEditorStore(SAVED);
    const heard: string[] = [];
    const stop = store.on('save', () => void heard.push('save'));
    store.on('test', () => void heard.push('test'));
    store.on('pages', () => void heard.push('pages'));
    store.request('save');
    store.request('test');
    store.request('pages');
    stop();
    store.request('save');
    assert.deepEqual(heard, ['save', 'test', 'pages']);
});
