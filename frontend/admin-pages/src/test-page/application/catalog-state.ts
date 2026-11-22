/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the "Whole catalog" mode shows, and how it changes: where the check stands, the findings of the last one, the
 * drawer of one finding with a part of its pages, and the message about what just happened.
 *
 * - "Check now" answers where the check stands; while a check is on its way the page asks again every few seconds.
 *   Each answer re-arms the next question; a question the session or the role refuses stops them and says so, any
 *   other failure is asked again.
 * - When a check the page watched has ended, the page says so (or that it stopped) and reads the findings anew; the
 *   findings shown stay until the new ones arrive, and a drawer whose finding is gone from them closes.
 * - Opening a finding reads the first part of its pages; another part replaces it. An answer for a drawer no longer
 *   open is dropped.
 * - The download keeps the drawer open: a success says so in the page's message, a failure in the drawer.
 *
 * Every event has its own handler in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import { runEnded } from '../domain/catalog-view.ts';
import type { CheckStatus, FindingItems, FindingsTable } from '../domain/model.ts';

/** The findings on screen: shown, being read anew, or that read failed. */
export type FindingsState =
    | { readonly status: 'ready'; readonly table: FindingsTable }
    | { readonly status: 'loading'; readonly table: FindingsTable }
    | { readonly status: 'failed'; readonly table: FindingsTable; readonly failure: RequestFailure };

/** A part of the pages behind the finding in the drawer: on its way, in, or that read failed. */
export type ItemsState =
    | { readonly status: 'loading' }
    | { readonly status: 'ready'; readonly items: FindingItems }
    | { readonly status: 'failed'; readonly failure: RequestFailure };

export interface FindingDrawer {
    readonly key: string;
    /** The part of the list shown, from 1. */
    readonly page: number;
    readonly items: ItemsState;
    readonly downloading: boolean;
    readonly downloadFailure: RequestFailure | null;
}

interface Toasts {
    /** A check the page watched has ended. */
    ended: { readonly tone: 'success' | 'error'; readonly text: string };
    /** The pages behind a finding were saved. */
    saved: { readonly text: string };
    /** A request of the page failed: "Check now", or asking where the check stands. */
    failed: { readonly failure: RequestFailure; readonly place: 'test.check.failed' | 'test.check.statusFailed' };
}

export type ToastKind = keyof Toasts;

export type Toast<K extends ToastKind = ToastKind> = { [P in K]: { readonly kind: P } & Toasts[P] }[K];

export interface CatalogState {
    readonly status: CheckStatus;
    readonly findings: FindingsState;
    /** "Check now" is on its way. */
    readonly asking: boolean;
    /** Counts the answers about where the check stands, so each one re-arms the next question. */
    readonly polls: number;
    /** Whether the page may go on asking where a check on its way stands. */
    readonly watching: boolean;
    readonly drawer: FindingDrawer | null;
    readonly toast: Toast | null;
}

interface CatalogEvents {
    checkAsked: Record<never, never>;
    checkAnswered: { readonly status: CheckStatus };
    checkFailed: { readonly failure: RequestFailure };
    statusLoaded: { readonly status: CheckStatus };
    statusFailed: { readonly failure: RequestFailure; readonly retry: boolean };
    findingsLoaded: { readonly table: FindingsTable };
    findingsFailed: { readonly failure: RequestFailure };
    drawerOpened: { readonly key: string };
    drawerClosed: Record<never, never>;
    itemsAsked: { readonly page: number };
    itemsLoaded: { readonly key: string; readonly items: FindingItems };
    itemsFailed: { readonly key: string; readonly failure: RequestFailure };
    downloadStarted: Record<never, never>;
    downloadDone: { readonly key: string; readonly text: string };
    downloadFailed: { readonly key: string; readonly failure: RequestFailure };
    toastDismissed: Record<never, never>;
}

export type CatalogEventKind = keyof CatalogEvents;

export type CatalogEvent<K extends CatalogEventKind = CatalogEventKind> =
    { [P in K]: { readonly type: P } & CatalogEvents[P] }[K];

/** The drawer changed by `change` while it is still the one of `key`; any other state is left as it is. */
function inDrawer(state: CatalogState, key: string, change: (drawer: FindingDrawer) => FindingDrawer): CatalogState {
    return state.drawer !== null && state.drawer.key === key ? { ...state, drawer: change(state.drawer) } : state;
}

const handlers: { readonly [K in CatalogEventKind]: (state: CatalogState, event: CatalogEvent<K>) => CatalogState } = {
    checkAsked: (state) => ({ ...state, asking: true, toast: null }),
    checkAnswered: (state, event) => ({
        ...state,
        status: event.status,
        asking: false,
        polls: state.polls + 1,
        watching: true,
    }),
    checkFailed: (state, event) => ({
        ...state,
        asking: false,
        toast: { kind: 'failed', failure: event.failure, place: 'test.check.failed' },
    }),
    statusLoaded: (state, event) => {
        const ended = runEnded(state.status, event.status);
        return {
            ...state,
            status: event.status,
            polls: state.polls + 1,
            findings: ended === null ? state.findings : { status: 'loading', table: state.findings.table },
            toast: ended === null ? state.toast : { kind: 'ended', ...ended },
        };
    },
    statusFailed: (state, event) => (event.retry
        ? { ...state, polls: state.polls + 1 }
        : { ...state, watching: false, toast: { kind: 'failed', failure: event.failure, place: 'test.check.statusFailed' } }),
    findingsLoaded: (state, event) => ({
        ...state,
        findings: { status: 'ready', table: event.table },
        drawer: state.drawer !== null && event.table.findings.some((finding) => finding.key === state.drawer?.key)
            ? state.drawer
            : null,
    }),
    findingsFailed: (state, event) => ({
        ...state,
        findings: { status: 'failed', table: state.findings.table, failure: event.failure },
    }),
    drawerOpened: (state, event) => ({
        ...state,
        drawer: { key: event.key, page: 1, items: { status: 'loading' }, downloading: false, downloadFailure: null },
    }),
    drawerClosed: (state) => ({ ...state, drawer: null }),
    itemsAsked: (state, event) => (state.drawer === null
        ? state
        : { ...state, drawer: { ...state.drawer, page: event.page, items: { status: 'loading' } } }),
    itemsLoaded: (state, event) => inDrawer(state, event.key, (drawer) => ({
        ...drawer,
        page: event.items.page,
        items: { status: 'ready', items: event.items },
    })),
    itemsFailed: (state, event) => inDrawer(state, event.key, (drawer) => ({
        ...drawer,
        items: { status: 'failed', failure: event.failure },
    })),
    downloadStarted: (state) => (state.drawer === null
        ? state
        : { ...state, drawer: { ...state.drawer, downloading: true, downloadFailure: null } }),
    downloadDone: (state, event) => ({
        ...inDrawer(state, event.key, (drawer) => ({ ...drawer, downloading: false })),
        toast: { kind: 'saved', text: event.text },
    }),
    downloadFailed: (state, event) => inDrawer(state, event.key, (drawer) => ({
        ...drawer,
        downloading: false,
        downloadFailure: event.failure,
    })),
    toastDismissed: (state) => ({ ...state, toast: null }),
};

export function reduceCatalog<K extends CatalogEventKind>(state: CatalogState, event: CatalogEvent<K>): CatalogState {
    return handlers[event.type](state, event);
}

/** The reducer the page's hook holds: every event of the mode. */
export const catalogReducer = (state: CatalogState, event: CatalogEvent): CatalogState => reduceCatalog(state, event);

/** The mode as printed: where the check stands and the findings, nothing open, watching any check on its way. */
export const initialCatalog = (status: CheckStatus, table: FindingsTable): CatalogState => ({
    status,
    findings: { status: 'ready', table },
    asking: false,
    polls: 0,
    watching: true,
    drawer: null,
    toast: null,
});
