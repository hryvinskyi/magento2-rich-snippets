/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the "One page" mode shows, and how it changes: the page picked and the store view it is read in, the test on
 * its way or the last one's outcome, and which of its blocks are open.
 *
 * - Picking a page, choosing another store view, pressing Test and the "Test" of a page behind a finding each start a
 *   test at once; the test before it is dropped, so only the newest one's answer is shown.
 * - A test's answer opens its first block and closes the others. A page picked from a list that does not carry its
 *   path takes the path the test read it at.
 * - A test the server could not run keeps the page and says why; the outcome of the last test is replaced.
 *
 * Every event has its own handler in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import type { PageTest, PickedPage } from '../domain/model.ts';
import { samePage, type RunLine } from '../domain/page-view.ts';

/** The test: none asked for yet, on its way, answered, or the server could not run it. */
export type RunState =
    | { readonly status: 'idle' }
    | { readonly status: 'busy' }
    | { readonly status: 'done'; readonly test: PageTest }
    | { readonly status: 'error'; readonly failure: RequestFailure };

export interface PageTestState {
    readonly page: PickedPage | null;
    readonly store: number;
    readonly run: RunState;
    /** The blocks open, by their place in the answer. */
    readonly opened: ReadonlySet<number>;
}

interface PageTestEvents {
    picked: { readonly page: PickedPage };
    storeChosen: { readonly store: number };
    started: Record<never, never>;
    pageAsked: { readonly page: PickedPage; readonly store: number };
    answered: { readonly test: PageTest };
    failed: { readonly failure: RequestFailure };
    blockToggled: { readonly index: number };
}

export type PageTestEventKind = keyof PageTestEvents;

export type PageTestEvent<K extends PageTestEventKind = PageTestEventKind> =
    { [P in K]: { readonly type: P } & PageTestEvents[P] }[K];

const BUSY: RunState = { status: 'busy' };

/** The page as the test read it, when the pick lacked its path. */
function withPath(page: PickedPage | null, test: PageTest): PickedPage | null {
    return page !== null && page.path === '' && samePage(page, test.page) ? { ...page, path: test.page.path } : page;
}

const handlers: { readonly [K in PageTestEventKind]: (state: PageTestState, event: PageTestEvent<K>) => PageTestState } = {
    picked: (state, event) => ({ ...state, page: event.page, run: BUSY }),
    storeChosen: (state, event) => ({ ...state, store: event.store, run: BUSY }),
    started: (state) => ({ ...state, run: BUSY }),
    pageAsked: (state, event) => ({ ...state, page: event.page, store: event.store, run: BUSY }),
    answered: (state, event) => ({
        ...state,
        page: withPath(state.page, event.test),
        run: { status: 'done', test: event.test },
        opened: new Set([0]),
    }),
    failed: (state, event) => ({ ...state, run: { status: 'error', failure: event.failure } }),
    blockToggled: (state, event) => {
        const opened = new Set(state.opened);
        if (!opened.delete(event.index)) {
            opened.add(event.index);
        }
        return { ...state, opened };
    },
};

export function reducePageTest<K extends PageTestEventKind>(state: PageTestState, event: PageTestEvent<K>): PageTestState {
    return handlers[event.type](state, event);
}

/** The reducer the page's hook holds: every event of the mode. */
export const pageTestReducer = (state: PageTestState, event: PageTestEvent): PageTestState => reducePageTest(state, event);

/** The mode as printed: the first page and its store view, nothing tested yet. */
export const initialPageTest = (page: PickedPage | null, store: number): PageTestState => ({
    page,
    store,
    run: { status: 'idle' },
    opened: new Set([0]),
});

/** What the bar says about the test; null while there is nothing to say. */
export function runLineOf(state: PageTestState): RunLine | null {
    const lines: { readonly [S in RunState['status']]: () => RunLine | null } = {
        idle: () => (state.page === null ? 'none' : null),
        busy: () => 'busy',
        done: () => (state.run.status === 'done' && state.run.test.failure !== null ? 'failed' : 'done'),
        error: () => 'error',
    };
    return lines[state.run.status]();
}
