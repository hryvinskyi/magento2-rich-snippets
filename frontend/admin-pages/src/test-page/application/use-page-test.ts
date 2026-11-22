/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the "One page" mode's state to the page test.
 *
 * - The first page is tested once the mode is shown to an admin who may run tests; a page opened in the other mode is
 *   tested when the admin first switches to this one.
 * - Each pick, store view, press of Test and "Test" of a page behind a finding starts a test and aborts the one before
 *   it, so a stale answer never overwrites a newer one.
 * - Leaving the page aborts the test in flight, and an answer that arrives after that is not shown.
 */
import { useEffect, useMemo, useReducer } from 'preact/hooks';
import { createLatestRequest, settle, type Logger } from '@hryvinskyi/admin-ui';
import type { PickedPage } from '../domain/model.ts';
import { initialPageTest, pageTestReducer, type PageTestState } from './page-test-state.ts';
import type { TestEndpoints } from './ports.ts';

export interface PageTestControls {
    readonly state: PageTestState;
    pick(page: PickedPage): void;
    chooseStore(store: number): void;
    retest(): void;
    /** Tests a page in a store view: the "Test" of a page behind a finding. */
    testPage(page: PickedPage, store: number): void;
    toggleBlock(index: number): void;
}

/**
 * @param active whether the mode is the one shown
 * @param canTest whether the admin may run tests; nothing is tested otherwise
 */
export function usePageTest(
    endpoints: TestEndpoints,
    logger: Logger,
    first: { readonly page: PickedPage | null; readonly store: number },
    active: boolean,
    canTest: boolean,
): PageTestControls {
    const [state, dispatch] = useReducer(pageTestReducer, first, (printed) => initialPageTest(printed.page, printed.store));
    const request = useMemo(
        () => createLatestRequest((error: unknown): void => logger.error('The page could not be tested.', error)),
        [logger],
    );
    useEffect(() => () => request.cancel(), [request]);

    const start = useMemo(() => (page: PickedPage, store: number): void => {
        void request.run((signal) => endpoints.run(page, store, signal)).then((outcome) => settle(outcome, {
            done: (test) => dispatch({ type: 'answered', test }),
            failed: (failure) => dispatch({ type: 'failed', failure }),
        }));
    }, [request, endpoints]);

    const { page, store } = state;
    const untested = state.run.status === 'idle';
    useEffect(() => {
        if (active && canTest && untested && page !== null) {
            dispatch({ type: 'started' });
            start(page, store);
        }
    }, [active, canTest, untested, page, store, start]);

    return useMemo(() => ({
        state,
        pick(picked: PickedPage): void {
            dispatch({ type: 'picked', page: picked });
            start(picked, state.store);
        },
        chooseStore(chosen: number): void {
            dispatch({ type: 'storeChosen', store: chosen });
            if (state.page !== null) {
                start(state.page, chosen);
            }
        },
        retest(): void {
            if (state.page !== null) {
                dispatch({ type: 'started' });
                start(state.page, state.store);
            }
        },
        testPage(asked: PickedPage, storeId: number): void {
            dispatch({ type: 'pageAsked', page: asked, store: storeId });
            start(asked, storeId);
        },
        toggleBlock(index: number): void {
            dispatch({ type: 'blockToggled', index });
        },
    }), [state, start]);
}
