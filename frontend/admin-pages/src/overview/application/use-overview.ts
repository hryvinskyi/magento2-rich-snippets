/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the Overview's state to its endpoint. Picking a store view reads its Overview; trying again reads the one
 * picked again. Each read is the newest: a new pick aborts the read before it, so a slow answer for an earlier pick
 * never replaces the answer for the latest one. Leaving the page aborts the read in flight.
 */
import { useEffect, useMemo, useReducer } from 'preact/hooks';
import { createLatestRequest, settle, type Logger } from '@hryvinskyi/admin-ui';
import type { OverviewEndpoints } from '../adapters/overview-endpoints.ts';
import type { OverviewData } from '../domain/model.ts';
import { initialOverview, overviewReducer, type OverviewState } from './overview-state.ts';

export interface OverviewControls {
    readonly state: OverviewState;
    chooseStore(store: number | null): void;
    retry(): void;
}

export function useOverview(endpoints: OverviewEndpoints, data: OverviewData, logger: Logger): OverviewControls {
    const [state, dispatch] = useReducer(overviewReducer, data, initialOverview);
    const request = useMemo(
        () => createLatestRequest((error: unknown): void => logger.error('The Overview could not be read.', error)),
        [logger],
    );
    useEffect(() => () => request.cancel(), [request]);

    return useMemo(() => {
        const load = (store: number | null): void => {
            void request.run((signal) => endpoints.read(store, signal)).then((outcome) => settle(outcome, {
                done: (answer) => dispatch({ type: 'loaded', data: answer }),
                failed: (failure) => dispatch({ type: 'failed', failure }),
            }));
        };
        return {
            state,
            chooseStore(store: number | null): void {
                dispatch({ type: 'storeChosen', store });
                load(store);
            },
            retry(): void {
                dispatch({ type: 'retried' });
                load(state.store);
            },
        };
    }, [state, endpoints, request]);
}
