/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The editor's previews and its save, each over a fake gateway that holds every request until the test answers it and
 * a fake timer the test moves on: rapid changes send one preview once they pause, a change aborts the preview on its
 * way and an older answer never replaces a newer one; a save sends the version it was made from, keeps one idempotency
 * key per intent to create, and leaves the message each answer calls for.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { RequestError } from '@hryvinskyi/admin-ui';
import { contractPreview, contractSave, fakeDrafts, fakeScheduler } from '../../test/editor-harness.tsx';
import type { Draft, PreviewResult, Sample } from '../domain/model.ts';
import { createEditorStore } from './editor-store.ts';
import { createPreviewRequests, type PreviewStatus } from './preview-requests.ts';
import { createSaveSession } from './save-session.ts';

const SAVED: Draft = {
    id: 3, code: 'product', name: 'Product', type: 'Product', template: '{"@type": "Product"}', isBlock: false, active: true,
    pageKinds: ['product'], conditions: '', storeIds: [0], origin: null, updatedAt: '2026-11-12 09:30:00', updatedBy: 'admin',
};

const SAMPLE: Sample = { ref: 'product:12', kind: 'product', id: 12, label: 'Field satchel', url: 'https://shop.test/a', path: '/a' };

const settle = (): Promise<void> => new Promise((resolve) => setImmediate(resolve));

function previews() {
    const gateway = fakeDrafts();
    const scheduler = fakeScheduler();
    const shown: PreviewResult[] = [];
    const statuses: PreviewStatus[] = [];
    const reported: unknown[] = [];
    const requests = createPreviewRequests({
        gateway,
        scheduler,
        pauseMs: 400,
        show: (preview) => void shown.push(preview),
        status: (status) => void statuses.push(status),
        report: (error) => void reported.push(error),
    });
    return { gateway, scheduler, shown, statuses, reported, requests };
}

const draftNamed = (template: string): Draft => ({ ...SAVED, template });

describe('previews', () => {
    it('sends one preview for rapid changes, once they pause', () => {
        const { gateway, scheduler, requests } = previews();
        requests.ask({ draft: draftNamed('{"a": 1}'), sample: SAMPLE, store: null });
        scheduler.advance(200);
        requests.ask({ draft: draftNamed('{"a": 12}'), sample: SAMPLE, store: null });
        scheduler.advance(200);
        requests.ask({ draft: draftNamed('{"a": 123}'), sample: SAMPLE, store: 2 });
        scheduler.advance(399);
        assert.equal(gateway.previews.length, 0);
        scheduler.advance(1);
        assert.equal(gateway.previews.length, 1);
        assert.equal(gateway.previews[0]?.args.draft.template, '{"a": 123}');
        assert.equal(gateway.previews[0]?.args.store, 2);
        assert.equal(scheduler.pending(), 0);
    });

    it('aborts the preview on its way when the draft changes, and shows only the latest answer', async () => {
        const { gateway, scheduler, shown, statuses, requests } = previews();
        requests.now({ draft: draftNamed('{"a": 1}'), sample: SAMPLE, store: null });
        const first = gateway.previews[0];
        requests.ask({ draft: draftNamed('{"a": 2}'), sample: SAMPLE, store: null });
        assert.equal(first?.signal.aborted, true);
        scheduler.advance(400);
        const second = gateway.previews[1];
        first?.answer(contractPreview('noType')(SAMPLE));
        second?.answer(contractPreview('output')(SAMPLE));
        await settle();
        assert.deepEqual(shown.map((preview) => preview.state), ['output']);
        assert.deepEqual(statuses, ['loading', 'waiting', 'loading', 'shown']);
    });

    it('asks at once for another sample, aborting what is on its way', () => {
        const { gateway, requests } = previews();
        requests.now({ draft: SAVED, sample: SAMPLE, store: null });
        requests.now({ draft: SAVED, sample: { ...SAMPLE, ref: 'product:14' }, store: null });
        assert.equal(gateway.previews[0]?.signal.aborted, true);
        assert.equal(gateway.previews[1]?.args.sample.ref, 'product:14');
    });

    it('says a request the server could not answer failed, and reports anything else as a fault too', async () => {
        const { gateway, statuses, reported, requests } = previews();
        requests.now({ draft: SAVED, sample: SAMPLE, store: null });
        gateway.previews[0]?.fail(new RequestError('timeout'));
        await settle();
        requests.now({ draft: SAVED, sample: SAMPLE, store: null });
        gateway.previews[1]?.fail(new TypeError('broken answer'));
        await settle();
        assert.deepEqual(statuses, ['loading', 'failed', 'loading', 'failed']);
        assert.equal(reported.length, 1);
    });

    it('drops what waits and aborts what is on its way when it stops', () => {
        const { gateway, scheduler, requests } = previews();
        requests.now({ draft: SAVED, sample: SAMPLE, store: null });
        requests.stop();
        requests.ask({ draft: SAVED, sample: SAMPLE, store: null });
        requests.stop();
        scheduler.advance(1000);
        assert.equal(gateway.previews.length, 1);
        assert.equal(gateway.previews[0]?.signal.aborted, true);
    });
});

function saving(saved: Draft = SAVED) {
    const store = createEditorStore(saved);
    const gateway = fakeDrafts();
    const created: Draft[] = [];
    const reported: unknown[] = [];
    let key = 0;
    const session = createSaveSession({
        store,
        gateway,
        keys: { next: () => `key-${String(++key)}` },
        created: (draft) => void created.push(draft),
        report: (error) => void reported.push(error),
    });
    return { store, gateway, created, reported, session };
}

describe('the save', () => {
    it('sends the draft with the version it was made from, and takes the snippet as stored', async () => {
        const { store, gateway, session } = saving();
        store.change((draft) => ({ ...draft, name: 'Bag' }));
        const done = session.save();
        assert.equal(session.state().kind, 'saving');
        assert.deepEqual(gateway.saves[0]?.args, { draft: store.state().draft, updatedAt: '2026-11-12 09:30:00', key: null });
        const stored = { ...store.state().draft, updatedAt: '2026-11-22 10:00:00' };
        gateway.saves[0]?.answer({ kind: 'saved', draft: stored });
        await done;
        assert.equal(store.state().saved, stored);
        assert.deepEqual(session.state(), { kind: 'saved', name: 'Bag', reach: 'live' });
    });

    it('says what the new version reaches: the snippets using a block, nowhere for a snippet switched off', async () => {
        for (const [draft, reach] of [[{ ...SAVED, isBlock: true }, 'block'], [{ ...SAVED, active: false }, 'off']] as const) {
            const { gateway, session } = saving(draft);
            const done = session.save();
            gateway.saves[0]?.answer({ kind: 'saved', draft });
            await done;
            assert.deepEqual(session.state(), { kind: 'saved', name: 'Product', reach });
        }
    });

    it('keeps one idempotency key for a new snippet until it is created, and tells of the creation', async () => {
        const { store, gateway, created, session } = saving({ ...SAVED, id: null, code: '', updatedAt: null });
        const first = session.save();
        gateway.saves[0]?.fail(new RequestError('timeout'));
        await first;
        assert.equal(session.state().kind, 'failed');
        const second = session.save();
        assert.equal(gateway.saves[1]?.args.key, gateway.saves[0]?.args.key);
        assert.equal(gateway.saves[1]?.args.key, 'key-1');
        const stored = { ...store.state().draft, id: 8, code: 'product-2', updatedAt: '2026-11-22 10:00:00' };
        gateway.saves[1]?.answer({ kind: 'saved', draft: stored });
        await second;
        assert.deepEqual(created, [stored]);
        const third = session.save();
        assert.equal(gateway.saves[2]?.args.key, null, 'a stored snippet carries no key');
        gateway.saves[2]?.answer({ kind: 'saved', draft: stored });
        await third;
    });

    it('does not send a draft without a type, and marks the type instead', async () => {
        const { store, gateway, session } = saving({ ...SAVED, type: '', template: '{}' });
        await session.save();
        assert.equal(gateway.saves.length, 0);
        assert.equal(store.state().typeError, true);
    });

    it('sends nothing while a save is on its way or the Code tab holds text that is not a template', async () => {
        const { store, gateway, session } = saving();
        void session.save();
        await session.save();
        assert.equal(gateway.saves.length, 1);
        gateway.saves[0]?.answer(contractSave('saved'));
        await settle();
        store.showCodeInvalid(true);
        await session.save();
        assert.equal(gateway.saves.length, 1);
    });

    it('leaves the server\'s sentence for a conflict, the first reason for a refusal, and marks a refused type', async () => {
        const { store, gateway, session } = saving();
        const conflict = session.save();
        gateway.saves[0]?.answer(contractSave('conflict'));
        await conflict;
        const state = session.state();
        assert.ok(state.kind === 'conflict' && state.message.startsWith('Someone else saved this snippet at '));
        const refused = session.save();
        gateway.saves[1]?.answer(contractSave('refused'));
        await refused;
        assert.deepEqual(session.state(), { kind: 'refused', message: 'Enter a schema.org type, for example Product.' });
        assert.equal(store.state().typeError, true);
    });

    it('takes the message away when the draft changes after it, or when it is dismissed', async () => {
        const { store, gateway, session } = saving();
        const first = session.save();
        gateway.saves[0]?.answer({ kind: 'saved', draft: SAVED });
        await first;
        store.showPreview(contractPreview('output')(SAMPLE));
        assert.equal(session.state().kind, 'saved', 'a preview changes no draft');
        store.change((draft) => ({ ...draft, name: 'Bag' }));
        assert.equal(session.state().kind, 'idle');
        const second = session.save();
        gateway.saves[1]?.fail(new RequestError('network'));
        await second;
        session.dismiss();
        assert.equal(session.state().kind, 'idle');
    });

    it('aborts a save on its way when it stops, leaving no message', async () => {
        const { gateway, session } = saving();
        const done = session.save();
        session.stop();
        await done;
        assert.equal(gateway.saves[0]?.signal.aborted, true);
        assert.notEqual(session.state().kind, 'failed');
    });
});
