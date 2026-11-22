/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How the Snippets page and its import drawer move from one state to the next: a list answered for a store view no
 * longer picked is dropped, a switch shows the state wanted until its answer, a block in use turns the delete question
 * into the list of its users, and the drawer goes from choosing a file to its preview and back.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { RequestError, failureOf } from '@hryvinskyi/admin-ui';
import { parseImportPreview, parseListing } from '../adapters/snippets-data.ts';
import { contractList, emptyList, importPreview, listChanging } from '../../test/snippets-fixtures.ts';
import type { WriteAnswer } from '../domain/model.ts';
import { initialImport, reduceImport, type ImportState } from './import-state.ts';
import { initialSnippets, reduceSnippets, shownActive, type SnippetsState } from './snippets-state.ts';

const listing = () => parseListing(contractList(), '$');
const failure = failureOf(new RequestError('server'));

const answer = (refused: WriteAnswer['refused'] = null, list = listing()): WriteAnswer => ({ list, id: null, refused });

describe('the Snippets page\'s state', () => {
    it('loads the list of the store view picked, puts the filter back to every snippet and closes what was open', () => {
        const open: SnippetsState = { ...initialSnippets(listing()), filter: { kind: 'blocks' }, menu: 3, confirm: { id: 9, includers: null } };

        const picked = reduceSnippets(open, { type: 'storeChosen', store: 2 });

        assert.equal(picked.store, 2);
        assert.deepEqual(picked.filter, { kind: 'all' });
        assert.equal(picked.list.status, 'loading');
        assert.equal(picked.menu, null);
        assert.equal(picked.confirm, null);
    });

    it('drops a list answered for a store view that is no longer picked', () => {
        const picked = reduceSnippets(initialSnippets(listing()), { type: 'storeChosen', store: 2 });
        const stale = reduceSnippets(picked, { type: 'listLoaded', store: 1, listing: parseListing(emptyList(), '$') });
        const fresh = reduceSnippets(picked, { type: 'listLoaded', store: 2, listing: parseListing(emptyList(), '$') });

        assert.equal(stale.list.status, 'loading');
        assert.equal(fresh.list.status, 'ready');
        assert.equal(fresh.list.listing.rows.length, 0);
    });

    it('keeps the list on screen while it fails and while it is read again', () => {
        const failed = reduceSnippets(initialSnippets(listing()), { type: 'listFailed', failure });
        const retried = reduceSnippets(failed, { type: 'retried' });

        assert.equal(failed.list.status, 'failed');
        assert.equal(failed.list.listing.rows.length, 6);
        assert.equal(retried.list.status, 'loading');
    });

    it('shows the state a switch was asked for until its answer, then the server\'s list', () => {
        const asked = reduceSnippets(initialSnippets(listing()), { type: 'switchAsked', id: 6, active: true });

        assert.equal(shownActive(asked, 6, false), true);
        const answered = reduceSnippets(asked, {
            type: 'switchAnswered',
            id: 6,
            store: null,
            answer: answer(null, parseListing(listChanging('product-list', { active: true }), '$')),
        });
        assert.equal(answered.switching.size, 0);
        assert.equal(answered.list.listing.rows.find((row) => row.id === 6)?.active, true);
    });

    it('puts a switch back and says so when its request fails or the server refuses it', () => {
        const asked = reduceSnippets(initialSnippets(listing()), { type: 'switchAsked', id: 6, active: true });

        const failed = reduceSnippets(asked, { type: 'switchFailed', id: 6, failure });
        assert.equal(shownActive(failed, 6, false), false);
        assert.deepEqual(failed.toast, { kind: 'failed', act: 'switch', failure });

        const refused = reduceSnippets(asked, {
            type: 'switchAnswered',
            id: 6,
            store: null,
            answer: answer({ reason: 'template_invalid', includers: [] }),
        });
        assert.equal(shownActive(refused, 6, false), false);
        assert.deepEqual(refused.toast, { kind: 'refused', reason: 'template_invalid' });
    });

    it('says a copy was added, or why it was not', () => {
        const copied = reduceSnippets(initialSnippets(listing()), { type: 'duplicated', store: null, answer: answer(), name: 'FAQ' });
        const refused = reduceSnippets(initialSnippets(listing()), {
            type: 'duplicated',
            store: null,
            answer: answer({ reason: 'unknown_snippet', includers: [] }),
            name: 'FAQ',
        });

        assert.deepEqual(copied.toast, { kind: 'duplicated', name: 'FAQ' });
        assert.deepEqual(refused.toast, { kind: 'refused', reason: 'unknown_snippet' });
    });

    it('asks before deleting, keeps the snippet when told to, and says it was deleted after the answer', () => {
        const asked = reduceSnippets({ ...initialSnippets(listing()), menu: 8 }, { type: 'deleteAsked', id: 8 });
        assert.deepEqual(asked.confirm, { id: 8, includers: null });
        assert.equal(asked.menu, null);
        assert.equal(reduceSnippets(asked, { type: 'deleteKept' }).confirm, null);

        const started = reduceSnippets(asked, { type: 'deleteStarted', id: 8 });
        assert.equal(started.deleting, 8);
        const deleted = reduceSnippets(started, { type: 'deleteAnswered', id: 8, store: null, answer: answer(), name: 'FAQ' });
        assert.equal(deleted.deleting, null);
        assert.equal(deleted.confirm, null);
        assert.deepEqual(deleted.toast, { kind: 'deleted', name: 'FAQ' });
    });

    it('turns the question into the snippets that use a block when the server refuses to delete it', () => {
        const started = reduceSnippets(reduceSnippets(initialSnippets(listing()), { type: 'deleteAsked', id: 9 }), { type: 'deleteStarted', id: 9 });
        const includers = [{ code: 'faq', name: 'FAQ' }];

        const inUse = reduceSnippets(started, {
            type: 'deleteAnswered',
            id: 9,
            store: null,
            answer: answer({ reason: 'in_use', includers }),
            name: 'Return policy',
        });

        assert.deepEqual(inUse.confirm, { id: 9, includers });
        assert.equal(inUse.deleting, null);
        assert.equal(inUse.toast, null);
    });

    it('stops showing a delete on its way when it fails, and says what failed', () => {
        const started = reduceSnippets(initialSnippets(listing()), { type: 'deleteStarted', id: 8 });
        const failed = reduceSnippets(started, { type: 'writeFailed', act: 'delete', failure });

        assert.equal(failed.deleting, null);
        assert.deepEqual(failed.toast, { kind: 'failed', act: 'delete', failure });
    });

    it('closes the question when a menu opens, and leaves it when the menu closes', () => {
        const asked = reduceSnippets(initialSnippets(listing()), { type: 'deleteAsked', id: 8 });

        assert.equal(reduceSnippets(asked, { type: 'menuToggled', id: 3 }).confirm, null);
        assert.deepEqual(reduceSnippets(asked, { type: 'menuToggled', id: null }).confirm, { id: 8, includers: null });
    });

    it('takes the list an import answered and says how many snippets came from which file', () => {
        const imported = reduceSnippets(initialSnippets(parseListing(emptyList(), '$')), {
            type: 'imported',
            store: null,
            listing: listing(),
            count: 3,
            fileName: 'snippets.json',
        });

        assert.equal(imported.list.listing.rows.length, 6);
        assert.deepEqual(imported.toast, { kind: 'imported', count: 3, fileName: 'snippets.json' });
        assert.equal(reduceSnippets(imported, { type: 'toastDismissed' }).toast, null);
    });
});

describe('the import drawer\'s state', () => {
    const preview = parseImportPreview(importPreview(), '$');
    const file = { name: 'snippets.json', size: 2048, text: '{}' };
    const previewed = (): ImportState =>
        reduceImport(reduceImport(initialImport(), { type: 'opened' }), { type: 'previewed', file, preview, idempotencyKey: 'key-1' });

    it('opens on choosing a file, reads it, and shows its preview with the server\'s first choices', () => {
        const opened = reduceImport(initialImport(), { type: 'opened' });
        assert.equal(opened.step, 'choosing');
        assert.deepEqual(reduceImport(opened, { type: 'fileChosen', name: 'snippets.json', size: 2048 }), { step: 'reading', name: 'snippets.json', size: 2048 });

        const shown = previewed();
        assert.equal(shown.step, 'preview');
        assert.equal(shown.step === 'preview' ? shown.choices.get('breadcrumbs') : null, 'add');
    });

    it('takes a new key with every change of choice, so a commit after a change is a new request', () => {
        const changed = reduceImport(previewed(), { type: 'choiceChanged', code: 'product', choice: 'skip', idempotencyKey: 'key-2' });

        assert.equal(changed.step === 'preview' ? changed.choices.get('product') : null, 'skip');
        assert.equal(changed.step === 'preview' ? changed.idempotencyKey : null, 'key-2');
    });

    it('shows a commit on its way and its failure, keeping the choices', () => {
        const started = reduceImport(previewed(), { type: 'commitStarted' });
        assert.equal(started.step === 'preview' && started.committing, true);

        const failed = reduceImport(started, { type: 'commitFailed', failure });
        assert.equal(failed.step === 'preview' && failed.committing, false);
        assert.equal(failed.step === 'preview' ? failed.failure : null, failure);
        assert.equal(failed.step === 'preview' ? failed.choices.size : 0, 4);
    });

    it('shows a refused file with its name and size, and closes on any step', () => {
        const refused = reduceImport(previewed(), { type: 'refused', name: 'bad.json', size: 10, failure });

        assert.equal(refused.step, 'refused');
        assert.equal(reduceImport(refused, { type: 'closed' }).step, 'closed');
    });

    it('ignores a choice or a commit while no preview is shown', () => {
        const opened = reduceImport(initialImport(), { type: 'opened' });

        assert.equal(reduceImport(opened, { type: 'choiceChanged', code: 'product', choice: 'skip', idempotencyKey: 'key-9' }), opened);
        assert.equal(reduceImport(opened, { type: 'commitStarted' }), opened);
    });
});
