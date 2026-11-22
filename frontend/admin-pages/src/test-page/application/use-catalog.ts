/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the "Whole catalog" mode's state to the check's endpoints.
 *
 * - While a check is on its way, the page asks where it stands every few seconds, one question at a time: each answer
 *   arms the next, and a check that is no longer on its way arms none. When a check the page watched has ended, the
 *   findings are read anew.
 * - "Check now" asks for a check, unless one is being asked for.
 * - Opening a finding reads the first part of its pages, aborting the read of a drawer opened before; the finding the
 *   address names opens once the page is drawn. The download saves every page behind the finding.
 * - Leaving the page cancels the next question and aborts what is in flight; an answer that arrives after that is
 *   not shown.
 */
import { useEffect, useMemo, useReducer } from 'preact/hooks';
import { canRetry, createLatestRequest, settle, type Logger } from '@hryvinskyi/admin-ui';
import { isChecking, POLL_MS } from '../domain/catalog-view.ts';
import type { CheckStatus, Finding, FindingsTable } from '../domain/model.ts';
import { catalogReducer, initialCatalog, type CatalogState } from './catalog-state.ts';
import type { FindingsDownloads, Scheduler, TestEndpoints } from './ports.ts';

export interface CatalogPorts {
    readonly endpoints: TestEndpoints;
    readonly downloads: FindingsDownloads;
    readonly scheduler: Scheduler;
    readonly logger: Logger;
}

export interface CatalogControls {
    readonly state: CatalogState;
    checkNow(): void;
    open(finding: Finding): void;
    close(): void;
    showPart(page: number): void;
    download(finding: Finding): void;
    dismissToast(): void;
}

export interface PrintedCatalog {
    readonly status: CheckStatus;
    readonly findings: FindingsTable;
    /** The store view the findings are of; null for every one. */
    readonly store: number | null;
    /** The finding whose pages open once the page is drawn; null for none. */
    readonly opening: string | null;
}

export function useCatalog(ports: CatalogPorts, printed: PrintedCatalog): CatalogControls {
    const { endpoints, downloads, scheduler, logger } = ports;
    const { store, opening } = printed;
    const [state, dispatch] = useReducer(catalogReducer, printed, (first) => initialCatalog(first.status, first.findings));
    const requests = useMemo(() => ({
        status: createLatestRequest((error: unknown): void => logger.error('Where the check stands could not be read.', error)),
        check: createLatestRequest((error: unknown): void => logger.error('The check could not be asked for.', error)),
        findings: createLatestRequest((error: unknown): void => logger.error('The findings could not be read.', error)),
        items: createLatestRequest((error: unknown): void => logger.error('The pages of a finding could not be read.', error)),
        download: createLatestRequest((error: unknown): void => logger.error('The pages of a finding could not be saved.', error)),
    }), [logger]);
    useEffect(() => () => Object.values(requests).forEach((request) => request.cancel()), [requests]);

    const loadFindings = useMemo(() => (): void => {
        void requests.findings.run((signal) => endpoints.findings(store, signal)).then((outcome) => settle(outcome, {
            done: (table) => dispatch({ type: 'findingsLoaded', table }),
            failed: (failure) => dispatch({ type: 'findingsFailed', failure }),
        }));
    }, [requests, endpoints, store]);

    const loadItems = useMemo(() => (key: string, page: number): void => {
        void requests.items.run((signal) => endpoints.findingItems(key, store, page, signal)).then((outcome) => settle(outcome, {
            done: (items) => dispatch({ type: 'itemsLoaded', key, items }),
            failed: (failure) => dispatch({ type: 'itemsFailed', key, failure }),
        }));
    }, [requests, endpoints, store]);

    const before = state.status;
    const watching = state.watching && isChecking(before);
    useEffect(() => {
        if (!watching) {
            return undefined;
        }
        return scheduler.after(POLL_MS, () => {
            void requests.status.run((signal) => endpoints.status(signal)).then((outcome) => settle(outcome, {
                done: (status) => {
                    dispatch({ type: 'statusLoaded', status });
                    if (!isChecking(status)) {
                        loadFindings();
                    }
                },
                failed: (failure) => dispatch({ type: 'statusFailed', failure, retry: canRetry(failure) }),
            }));
        });
    }, [watching, state.polls, before, scheduler, requests, endpoints, loadFindings]);

    const open = useMemo(() => (key: string): void => {
        dispatch({ type: 'drawerOpened', key });
        loadItems(key, 1);
    }, [loadItems]);

    useEffect(() => {
        if (opening !== null) {
            open(opening);
        }
    }, [opening, open]);

    return useMemo(() => ({
        state,
        checkNow(): void {
            if (state.asking) {
                return;
            }
            dispatch({ type: 'checkAsked' });
            void requests.check.run((signal) => endpoints.requestCheck(signal)).then((outcome) => settle(outcome, {
                done: (status) => dispatch({ type: 'checkAnswered', status }),
                failed: (failure) => dispatch({ type: 'checkFailed', failure }),
            }));
        },
        open(finding: Finding): void {
            open(finding.key);
        },
        close(): void {
            requests.items.cancel();
            requests.download.cancel();
            dispatch({ type: 'drawerClosed' });
        },
        showPart(page: number): void {
            if (state.drawer !== null) {
                dispatch({ type: 'itemsAsked', page });
                loadItems(state.drawer.key, page);
            }
        },
        download(finding: Finding): void {
            if (state.drawer === null || state.drawer.downloading) {
                return;
            }
            const { key } = finding;
            dispatch({ type: 'downloadStarted' });
            void requests.download.run((signal) => downloads.download(finding, store, signal)).then((outcome) => settle(outcome, {
                done: () => dispatch({ type: 'downloadDone', key, text: finding.saved }),
                failed: (failure) => dispatch({ type: 'downloadFailed', key, failure }),
            }));
        },
        dismissToast(): void {
            dispatch({ type: 'toastDismissed' });
        },
    }), [state, requests, endpoints, downloads, store, open, loadItems]);
}
