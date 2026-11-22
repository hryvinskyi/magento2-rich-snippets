/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the page picker's list to the search: typing waits a moment before it asks, so a word typed fast asks once;
 * each search aborts the one before it, so the list always answers the newest text; leaving the page cancels both
 * the wait and the search.
 */
import { useEffect, useMemo, useState } from 'preact/hooks';
import { createLatestRequest, settle, type Logger, type RequestFailure } from '@hryvinskyi/admin-ui';
import type { PickedPage } from '../domain/model.ts';
import { searchOptions } from '../domain/page-view.ts';
import type { Scheduler, TestEndpoints } from './ports.ts';

/** How long typing rests before the picker searches. */
export const SEARCH_PAUSE_MS = 250;

/** The picker's list: nothing asked yet, on its way, the pages found, or the search failed. */
export type SearchState =
    | { readonly status: 'idle' }
    | { readonly status: 'loading' }
    | { readonly status: 'ready'; readonly options: readonly PickedPage[] }
    | { readonly status: 'failed'; readonly failure: RequestFailure };

export interface PageSearch {
    readonly state: SearchState;
    /** Searches a text; at once, or after typing has rested. */
    find(query: string, store: number, now: boolean): void;
    /** Drops the search in flight and the one waiting. */
    stop(): void;
}

export function usePageSearch(endpoints: TestEndpoints, scheduler: Scheduler, logger: Logger): PageSearch {
    const [state, setState] = useState<SearchState>({ status: 'idle' });
    const runner = useMemo(() => {
        const request = createLatestRequest((error: unknown): void => logger.error('The pages could not be searched.', error));
        let waiting: (() => void) | null = null;
        const stopWaiting = (): void => {
            waiting?.();
            waiting = null;
        };
        const ask = (query: string, store: number): void => {
            setState({ status: 'loading' });
            void request.run((signal) => endpoints.search(query, store, signal)).then((outcome) => settle(outcome, {
                done: (answer) => setState({ status: 'ready', options: searchOptions(answer) }),
                failed: (failure) => setState({ status: 'failed', failure }),
            }));
        };
        return {
            find(query: string, store: number, now: boolean): void {
                stopWaiting();
                if (now) {
                    ask(query, store);
                } else {
                    waiting = scheduler.after(SEARCH_PAUSE_MS, () => {
                        waiting = null;
                        ask(query, store);
                    });
                }
            },
            stop(): void {
                stopWaiting();
                request.cancel();
            },
        };
    }, [endpoints, scheduler, logger]);
    useEffect(() => () => runner.stop(), [runner]);
    return useMemo(() => ({ state, find: runner.find, stop: runner.stop }), [state, runner]);
}
