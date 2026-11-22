/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Overview shows, and how it changes: the store view picked, the Overview last read, and where the numbers
 * of the store view picked stand.
 *
 * Picking a store view or trying again asks for its Overview; until it arrives the page keeps the last one it read,
 * and only the Google card says the numbers are on their way. An answer replaces the whole Overview; a refusal keeps
 * the last one and lets the Google card say so. Every event has its own handler in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import type { OverviewData } from '../domain/model.ts';
import type { NumbersState } from '../domain/overview-view.ts';

export interface OverviewState {
    /** null for every store view together. */
    readonly store: number | null;
    readonly shown: OverviewData;
    readonly numbers: NumbersState;
    /** Why the numbers of the store view picked could not be loaded; null unless they failed. */
    readonly failure: RequestFailure | null;
}

interface OverviewEvents {
    storeChosen: { readonly store: number | null };
    retried: Record<never, never>;
    loaded: { readonly data: OverviewData };
    failed: { readonly failure: RequestFailure };
}

export type OverviewEvent<K extends keyof OverviewEvents = keyof OverviewEvents> =
    { [P in K]: { readonly type: P } & OverviewEvents[P] }[K];

const handlers: { [K in keyof OverviewEvents]: (state: OverviewState, event: OverviewEvent<K>) => OverviewState } = {
    storeChosen: (state, event) => ({ ...state, store: event.store, numbers: 'loading', failure: null }),
    retried: (state) => ({ ...state, numbers: 'loading', failure: null }),
    loaded: (state, event) => ({ ...state, shown: event.data, numbers: 'ready', failure: null }),
    failed: (state, event) => ({ ...state, numbers: 'failed', failure: event.failure }),
};

export function reduceOverview<K extends keyof OverviewEvents>(state: OverviewState, event: OverviewEvent<K>): OverviewState {
    return handlers[event.type](state, event);
}

/** The reducer the page's hook holds: every event of the page. */
export const overviewReducer = (state: OverviewState, event: OverviewEvent): OverviewState => reduceOverview(state, event);

/** The page as printed: the store view of the first data, its numbers already in. */
export const initialOverview = (data: OverviewData): OverviewState =>
    ({ store: data.store, shown: data, numbers: 'ready', failure: null });
