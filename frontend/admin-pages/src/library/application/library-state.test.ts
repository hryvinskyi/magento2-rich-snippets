/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How the Library page moves from one state to the next: a search puts the chip back to every group, a read anew
 * keeps the library on screen, the drawer reads its questions and drops answers for a drawer no longer open, an add
 * keeps its key through a failure and closes the drawer with a message on success, and the recommended set keeps its
 * key until it succeeds.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { failureOf, RequestError } from '@hryvinskyi/admin-ui';
import { parseAddAnswer, parseLibrary, parseTemplateAnswers } from '../adapters/library-data.ts';
import { contractAdd, contractLibrary, contractSuggestions } from '../../test/library-fixtures.ts';
import { initialLibrary, reduceLibrary, type LibraryState } from './library-state.ts';

const library = () => parseLibrary(contractLibrary('first'), '$');
const failure = failureOf(new RequestError('server'));

const opened = (code = 'product', asks = true): LibraryState =>
    reduceLibrary(initialLibrary(library()), { type: 'drawerOpened', code, key: 'key-1', asks });

describe('the Library page\'s state', () => {
    it('puts the chip back to every group whenever the search changes', () => {
        const chosen = reduceLibrary(initialLibrary(library()), { type: 'groupChosen', group: 'blocks' });
        const typed = reduceLibrary(chosen, { type: 'searched', query: 'pro' });

        assert.equal(chosen.group, 'blocks');
        assert.deepEqual([typed.query, typed.group], ['pro', null]);
        assert.equal(reduceLibrary(reduceLibrary(typed, { type: 'groupChosen', group: 'shop' }), { type: 'searched', query: '' }).group, null);
    });

    it('starts as a failed read without a request failure when the page was printed without its library', () => {
        const state = initialLibrary(library(), true);

        assert.deepEqual([state.list.status, state.list.status === 'failed' ? state.list.failure : 'none'], ['failed', null]);
        assert.equal(reduceLibrary(state, { type: 'retried' }).list.status, 'loading');
    });

    it('keeps the library on screen while it is read anew and while that read fails, then takes the new one', () => {
        const first = library();
        const reading = reduceLibrary(initialLibrary(first), { type: 'retried' });
        const failed = reduceLibrary(reading, { type: 'listFailed', failure });
        const lib = parseLibrary(contractLibrary('lib'), '$');
        const loaded = reduceLibrary(failed, { type: 'listLoaded', library: lib });

        assert.deepEqual([reading.list.status, reading.list.library], ['loading', first]);
        assert.deepEqual([failed.list.status, failed.list.library], ['failed', first]);
        assert.deepEqual([loaded.list.status, loaded.list.library], ['ready', lib]);
    });

    it('opens a drawer with its key, its questions loading when it asks any, and the last message gone', () => {
        const toasted: LibraryState = { ...initialLibrary(library()), toast: { kind: 'failed', failure } };
        const asking = reduceLibrary(toasted, { type: 'drawerOpened', code: 'product', key: 'key-7', asks: true });
        const silent = reduceLibrary(toasted, { type: 'drawerOpened', code: 'faq', key: 'key-8', asks: false });

        assert.deepEqual(
            [asking.drawer?.code, asking.drawer?.key, asking.drawer?.questions.status, asking.drawer?.adding, asking.toast],
            ['product', 'key-7', 'loading', false, null],
        );
        assert.equal(silent.drawer?.questions.status, 'none');
        assert.equal(reduceLibrary(silent, { type: 'drawerClosed' }).drawer, null);
    });

    it('takes the answers read for the open drawer, and drops answers or failures read for another', () => {
        const answers = parseTemplateAnswers(contractSuggestions('product'), '$');
        const ready = reduceLibrary(opened(), { type: 'questionsLoaded', code: 'product', answers });
        const stale = reduceLibrary(opened(), { type: 'questionsLoaded', code: 'organization', answers });
        const staleFailure = reduceLibrary(opened(), { type: 'questionsFailed', code: 'organization', failure });
        const failed = reduceLibrary(opened(), { type: 'questionsFailed', code: 'product', failure });

        assert.equal(ready.drawer?.questions.status, 'ready');
        assert.equal(stale.drawer?.questions.status, 'loading');
        assert.equal(staleFailure.drawer?.questions.status, 'loading');
        assert.equal(failed.drawer?.questions.status, 'failed');
        assert.equal(reduceLibrary(failed, { type: 'questionsRetried' }).drawer?.questions.status, 'loading');
    });

    it('records each answer chosen by its question, the last choice winning', () => {
        const once = reduceLibrary(opened(), { type: 'answerChosen', question: 'brand', answer: 'shop' });
        const twice = reduceLibrary(once, { type: 'answerChosen', question: 'brand', answer: 'leave_out' });
        const other = reduceLibrary(twice, { type: 'answerChosen', question: 'gtin', answer: 'leave_out' });

        assert.deepEqual(Object.fromEntries(other.drawer?.chosen ?? []), { brand: 'leave_out', gtin: 'leave_out' });
        assert.equal(once.drawer?.chosen.get('brand'), 'shop', 'an earlier state is left as it was');
    });

    it('ignores the drawer\'s events while no drawer is open', () => {
        const closed = initialLibrary(library());

        assert.equal(reduceLibrary(closed, { type: 'answerChosen', question: 'brand', answer: 'shop' }), closed);
        assert.equal(reduceLibrary(closed, { type: 'questionsRetried' }), closed);
        assert.equal(reduceLibrary(closed, { type: 'addStarted' }), closed);
        assert.equal(reduceLibrary(closed, { type: 'addFailed', code: 'product', failure }), closed);
    });

    it('keeps the drawer and its key through a failed add, saying so, and clears the failure on the next try', () => {
        const adding = reduceLibrary(opened(), { type: 'addStarted' });
        const failed = reduceLibrary(adding, { type: 'addFailed', code: 'product', failure });
        const again = reduceLibrary(failed, { type: 'addStarted' });

        assert.equal(adding.drawer?.adding, true);
        assert.deepEqual([failed.drawer?.adding, failed.drawer?.failure, failed.drawer?.key], [false, failure, 'key-1']);
        assert.deepEqual([again.drawer?.failure, again.drawer?.key], [null, 'key-1']);
    });

    it('closes the drawer on an add, takes the library answered, and says what was added', () => {
        const answer = parseAddAnswer(contractAdd('added'), '$');
        const added = reduceLibrary(reduceLibrary(opened(), { type: 'addStarted' }), { type: 'addAnswered', code: 'product', answer });

        assert.equal(added.drawer, null);
        assert.equal(added.list.library, answer.library);
        assert.equal(added.toast?.kind, 'added');
        assert.equal(added.toast?.kind === 'added' ? added.toast.template.code : null, 'product');
    });

    it('keeps the drawer open with the reason when the server refuses the add, and takes its library', () => {
        const answer = parseAddAnswer(contractAdd('refused'), '$');
        const refused = reduceLibrary(reduceLibrary(opened(), { type: 'addStarted' }), { type: 'addAnswered', code: 'product', answer });

        assert.deepEqual([refused.drawer?.refused, refused.drawer?.adding], ['include_loop', false]);
        assert.equal(refused.toast, null);
        assert.equal(refused.list.library, answer.library);
        assert.equal(reduceLibrary(refused, { type: 'addStarted' }).drawer?.refused, null);
    });

    it('keeps the recommended set\'s key until it succeeds, and says how it went', () => {
        const started = reduceLibrary(initialLibrary(library()), { type: 'setStarted', key: 'key-3' });
        const failed = reduceLibrary(started, { type: 'setFailed', failure });
        const answer = parseAddAnswer(contractAdd('recommended'), '$');
        const done = reduceLibrary(reduceLibrary(failed, { type: 'setStarted', key: 'key-3' }), { type: 'setAnswered', answer });

        assert.deepEqual([started.setKey, started.settingUp], ['key-3', true]);
        assert.deepEqual([failed.setKey, failed.settingUp, failed.toast?.kind], ['key-3', false, 'failed']);
        assert.deepEqual([done.setKey, done.settingUp, done.toast?.kind], [null, false, 'set']);
        assert.equal(done.list.library, answer.library);
        assert.equal(reduceLibrary(done, { type: 'toastDismissed' }).toast, null);
    });
});
