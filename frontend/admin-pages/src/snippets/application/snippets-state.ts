/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the snippet list shows, and how it changes: the store view picked, the chip pressed, the list last read and
 * whether a newer one is on its way, the switches on their way, the open row menu, the question under a row before a
 * delete, and the message about the last act.
 *
 * - Picking a store view reads its list and resets the chip to every snippet; until the list arrives the card shows
 *   it is loading; a failure keeps the last list and lets the card say so, with *Try again*.
 * - A switch shows the state wanted at once and keeps it while the request is on its way; the answer replaces the
 *   list, a refusal or a failure puts the switch back as it was and says so.
 * - Every write answers the list after it, for the store view it was sent with; a list for a store view no longer
 *   picked is not shown.
 * - A delete asks first; a building block that snippets use cannot be deleted, and the question turns into the
 *   names of the snippets that use it when the server says so.
 *
 * Every event has its own handler in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import { ALL, type Filter } from '../domain/filters.ts';
import type { Listing, SnippetReference, WriteAnswer } from '../domain/model.ts';

/** The list on screen: shown, being replaced by the one of the store view picked, or that one failed. */
export type ListState =
    | { readonly status: 'ready'; readonly listing: Listing }
    | { readonly status: 'loading'; readonly listing: Listing }
    | { readonly status: 'failed'; readonly listing: Listing; readonly failure: RequestFailure };

/** The acts a request can fail for. */
export type Act = 'switch' | 'duplicate' | 'delete' | 'export';

interface Toasts {
    duplicated: { readonly name: string };
    exported: { readonly name: string; readonly fileName: string };
    exportedAll: { readonly count: number; readonly fileName: string };
    deleted: { readonly name: string };
    imported: { readonly count: number; readonly fileName: string };
    failed: { readonly act: Act; readonly failure: RequestFailure };
    refused: { readonly reason: string };
}

export type ToastKind = keyof Toasts;

/** The message about the last act, under the page's header. */
export type Toast<K extends ToastKind = ToastKind> = { [P in K]: { readonly kind: P } & Toasts[P] }[K];

/** The question under a row; `includers` are the snippets the server said use it, null until it says so. */
export interface Confirm {
    readonly id: number;
    readonly includers: readonly SnippetReference[] | null;
}

export interface SnippetsState {
    /** null for every store view. */
    readonly store: number | null;
    readonly filter: Filter;
    readonly list: ListState;
    /** The state wanted for each switch whose request is on its way, by snippet id. */
    readonly switching: ReadonlyMap<number, boolean>;
    /** The row whose menu is open. */
    readonly menu: number | null;
    readonly confirm: Confirm | null;
    /** The row whose delete is on its way. */
    readonly deleting: number | null;
    readonly toast: Toast | null;
}

interface SnippetsEvents {
    storeChosen: { readonly store: number | null };
    filterChosen: { readonly filter: Filter };
    listLoaded: { readonly store: number | null; readonly listing: Listing };
    listFailed: { readonly failure: RequestFailure };
    retried: Record<never, never>;
    menuToggled: { readonly id: number | null };
    switchAsked: { readonly id: number; readonly active: boolean };
    switchAnswered: { readonly id: number; readonly store: number | null; readonly answer: WriteAnswer };
    switchFailed: { readonly id: number; readonly failure: RequestFailure };
    duplicated: { readonly store: number | null; readonly answer: WriteAnswer; readonly name: string };
    deleteAsked: { readonly id: number };
    deleteKept: Record<never, never>;
    deleteStarted: { readonly id: number };
    deleteAnswered: {
        readonly id: number;
        readonly store: number | null;
        readonly answer: WriteAnswer;
        readonly name: string;
    };
    writeFailed: { readonly act: Act; readonly failure: RequestFailure };
    exported: { readonly toast: Toast<'exported' | 'exportedAll'> };
    imported: { readonly store: number | null; readonly listing: Listing; readonly count: number; readonly fileName: string };
    toastDismissed: Record<never, never>;
}

export type SnippetsEventKind = keyof SnippetsEvents;

export type SnippetsEvent<K extends SnippetsEventKind = SnippetsEventKind> =
    { [P in K]: { readonly type: P } & SnippetsEvents[P] }[K];

const IN_USE = 'in_use';

/** The list after a write, shown only while the store view it was sent with is still the one picked. */
function withListing(state: SnippetsState, store: number | null, listing: Listing): SnippetsState {
    return store === state.store ? { ...state, list: { status: 'ready', listing } } : state;
}

function without(switching: ReadonlyMap<number, boolean>, id: number): ReadonlyMap<number, boolean> {
    const next = new Map(switching);
    next.delete(id);
    return next;
}

const handlers: { readonly [K in SnippetsEventKind]: (state: SnippetsState, event: SnippetsEvent<K>) => SnippetsState } = {
    storeChosen: (state, event) => ({
        ...state,
        store: event.store,
        filter: ALL,
        list: { status: 'loading', listing: state.list.listing },
        menu: null,
        confirm: null,
    }),
    filterChosen: (state, event) => ({ ...state, filter: event.filter, menu: null, confirm: null }),
    listLoaded: (state, event) => withListing(state, event.store, event.listing),
    listFailed: (state, event) => ({ ...state, list: { status: 'failed', listing: state.list.listing, failure: event.failure } }),
    retried: (state) => ({ ...state, list: { status: 'loading', listing: state.list.listing } }),
    menuToggled: (state, event) => ({ ...state, menu: event.id, confirm: event.id === null ? state.confirm : null }),
    switchAsked: (state, event) => ({
        ...state,
        switching: new Map(state.switching).set(event.id, event.active),
        menu: null,
    }),
    switchAnswered: (state, event) => {
        const next = withListing({ ...state, switching: without(state.switching, event.id) }, event.store, event.answer.list);
        return event.answer.refused === null ? next : { ...next, toast: { kind: 'refused', reason: event.answer.refused.reason } };
    },
    switchFailed: (state, event) => ({
        ...state,
        switching: without(state.switching, event.id),
        toast: { kind: 'failed', act: 'switch', failure: event.failure },
    }),
    duplicated: (state, event) => ({
        ...withListing(state, event.store, event.answer.list),
        toast: event.answer.refused === null
            ? { kind: 'duplicated', name: event.name }
            : { kind: 'refused', reason: event.answer.refused.reason },
    }),
    deleteAsked: (state, event) => ({ ...state, confirm: { id: event.id, includers: null }, menu: null }),
    deleteKept: (state) => ({ ...state, confirm: null }),
    deleteStarted: (state, event) => ({ ...state, deleting: event.id }),
    deleteAnswered: (state, event) => {
        const next = { ...withListing(state, event.store, event.answer.list), deleting: null };
        const refused = event.answer.refused;
        if (refused === null) {
            return { ...next, confirm: null, toast: { kind: 'deleted', name: event.name } };
        }
        return refused.reason === IN_USE
            ? { ...next, confirm: { id: event.id, includers: refused.includers } }
            : { ...next, confirm: null, toast: { kind: 'refused', reason: refused.reason } };
    },
    writeFailed: (state, event) => ({
        ...state,
        deleting: event.act === 'delete' ? null : state.deleting,
        toast: { kind: 'failed', act: event.act, failure: event.failure },
    }),
    exported: (state, event) => ({ ...state, toast: event.toast }),
    imported: (state, event) => ({
        ...withListing(state, event.store, event.listing),
        toast: { kind: 'imported', count: event.count, fileName: event.fileName },
    }),
    toastDismissed: (state) => ({ ...state, toast: null }),
};

export function reduceSnippets<K extends SnippetsEventKind>(state: SnippetsState, event: SnippetsEvent<K>): SnippetsState {
    return handlers[event.type](state, event);
}

/** The reducer the page's hook holds: every event of the page. */
export const snippetsReducer = (state: SnippetsState, event: SnippetsEvent): SnippetsState => reduceSnippets(state, event);

/** The page as printed: every store view, every snippet, the first list already in. */
export const initialSnippets = (listing: Listing): SnippetsState => ({
    store: null,
    filter: ALL,
    list: { status: 'ready', listing },
    switching: new Map(),
    menu: null,
    confirm: null,
    deleting: null,
    toast: null,
});

/** The switch as it is shown: the state wanted while its request is on its way, else the list's. */
export const shownActive = (state: SnippetsState, id: number, active: boolean): boolean =>
    state.switching.get(id) ?? active;
