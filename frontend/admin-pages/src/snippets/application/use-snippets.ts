/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the snippet list's state to its endpoints.
 *
 * - Picking a store view reads its list; each read is the newest, so a slow answer for an earlier pick never replaces
 *   the answer for the latest one. *Show all snippets* goes back to every snippet of every store view.
 * - A switch posts the state wanted; a second click on the same switch while the first is on its way replaces it, and
 *   only the newest answer counts.
 * - A copy carries a key generated for the click, so a request repeated after a timeout copies nothing more.
 * - Every other write runs to its end, each on its own; leaving the page aborts what is in flight, and an answer that
 *   arrives after that is not shown.
 */
import { useEffect, useMemo, useReducer, useRef } from 'preact/hooks';
import {
    createLatestRequest,
    failureOf,
    isAnsweredRefusal,
    settle,
    type LatestRequest,
    type Logger,
    type RequestFailure,
} from '@hryvinskyi/admin-ui';
import type { Filter } from '../domain/filters.ts';
import type { Listing, SnippetRow } from '../domain/model.ts';
import type { ExportDownloads, IdempotencyKeys, SnippetsEndpoints } from './ports.ts';
import { initialSnippets, snippetsReducer, type Act, type SnippetsState } from './snippets-state.ts';

export interface SnippetsPorts {
    readonly endpoints: SnippetsEndpoints;
    readonly downloads: ExportDownloads;
    readonly keys: IdempotencyKeys;
    readonly logger: Logger;
}

export interface SnippetsControls {
    readonly state: SnippetsState;
    chooseStore(store: number | null): void;
    chooseFilter(filter: Filter): void;
    /** Every snippet of every store view. */
    showAll(): void;
    retry(): void;
    toggleMenu(id: number | null): void;
    setActive(row: SnippetRow, active: boolean): void;
    duplicate(row: SnippetRow): void;
    askDelete(row: SnippetRow): void;
    keep(): void;
    remove(row: SnippetRow): void;
    exportOne(row: SnippetRow): void;
    exportAll(): void;
    /** The list an import answered, and how many snippets it added or replaced from which file. */
    imported(store: number | null, listing: Listing, count: number, fileName: string): void;
    dismissToast(): void;
}

/**
 * Writes that run to their end, each on its own, all aborted when the page goes away. A fault is reported; a refusal
 * the server answered (a snippet that may not be deleted while another includes it) is worded, not reported.
 */
interface Writes {
    run<T>(act: Act, work: (signal: AbortSignal) => Promise<T>, done: (value: T) => void, failed: (failure: RequestFailure) => void): void;
    cancel(): void;
}

function createWrites(report: (act: Act, error: unknown) => void): Writes {
    const live = new Set<AbortController>();
    return {
        run(act, work, done, failed): void {
            const controller = new AbortController();
            live.add(controller);
            void work(controller.signal).then(
                (value) => {
                    if (!controller.signal.aborted) {
                        done(value);
                    }
                },
                (error: unknown) => {
                    if (controller.signal.aborted) {
                        return;
                    }
                    if (!isAnsweredRefusal(error)) {
                        report(act, error);
                    }
                    failed(failureOf(error));
                },
            ).finally(() => live.delete(controller));
        },
        cancel(): void {
            live.forEach((controller) => controller.abort());
            live.clear();
        },
    };
}

export function useSnippets(ports: SnippetsPorts, first: Listing): SnippetsControls {
    const { endpoints, downloads, keys, logger } = ports;
    const [state, dispatch] = useReducer(snippetsReducer, first, initialSnippets);
    const reads = useMemo(
        () => createLatestRequest((error: unknown): void => logger.error('The snippet list could not be read.', error)),
        [logger],
    );
    const writes = useMemo(
        () => createWrites((act: Act, error: unknown): void => logger.error(`The snippet ${act} failed.`, error)),
        [logger],
    );
    const switches = useRef(new Map<number, LatestRequest>());
    useEffect(() => () => {
        reads.cancel();
        writes.cancel();
        switches.current.forEach((request) => request.cancel());
    }, [reads, writes]);

    return useMemo(() => {
        const load = (store: number | null): void => {
            void reads.run((signal) => endpoints.list(store, signal)).then((outcome) => settle(outcome, {
                done: (listing) => dispatch({ type: 'listLoaded', store, listing }),
                failed: (failure) => dispatch({ type: 'listFailed', failure }),
            }));
        };
        const chooseStore = (store: number | null): void => {
            dispatch({ type: 'storeChosen', store });
            load(store);
        };
        const switchOf = (id: number): LatestRequest => {
            const known = switches.current.get(id);
            if (known !== undefined) {
                return known;
            }
            const created = createLatestRequest((error: unknown): void => logger.error('The switch could not be changed.', error));
            switches.current.set(id, created);
            return created;
        };
        const exportFailed = (failure: RequestFailure): void => dispatch({ type: 'writeFailed', act: 'export', failure });
        const store = state.store;

        return {
            state,
            chooseStore,
            chooseFilter(filter: Filter): void {
                dispatch({ type: 'filterChosen', filter });
            },
            showAll(): void {
                if (store === null) {
                    dispatch({ type: 'filterChosen', filter: { kind: 'all' } });
                    return;
                }
                chooseStore(null);
            },
            retry(): void {
                dispatch({ type: 'retried' });
                load(store);
            },
            toggleMenu(id: number | null): void {
                dispatch({ type: 'menuToggled', id });
            },
            setActive(row: SnippetRow, active: boolean): void {
                dispatch({ type: 'switchAsked', id: row.id, active });
                void switchOf(row.id).run((signal) => endpoints.setActive(row.id, active, store, signal)).then((outcome) => settle(outcome, {
                    done: (answer) => dispatch({ type: 'switchAnswered', id: row.id, store, answer }),
                    failed: (failure) => dispatch({ type: 'switchFailed', id: row.id, failure }),
                }));
            },
            duplicate(row: SnippetRow): void {
                const key = keys.next();
                writes.run(
                    'duplicate',
                    (signal) => endpoints.duplicate(row.id, key, store, signal),
                    (answer) => dispatch({ type: 'duplicated', store, answer, name: row.name }),
                    (failure) => dispatch({ type: 'writeFailed', act: 'duplicate', failure }),
                );
            },
            askDelete(row: SnippetRow): void {
                dispatch({ type: 'deleteAsked', id: row.id });
            },
            keep(): void {
                dispatch({ type: 'deleteKept' });
            },
            remove(row: SnippetRow): void {
                dispatch({ type: 'deleteStarted', id: row.id });
                writes.run(
                    'delete',
                    (signal) => endpoints.remove(row.id, store, signal),
                    (answer) => dispatch({ type: 'deleteAnswered', id: row.id, store, answer, name: row.name }),
                    (failure) => dispatch({ type: 'writeFailed', act: 'delete', failure }),
                );
            },
            exportOne(row: SnippetRow): void {
                writes.run(
                    'export',
                    (signal) => downloads.one(row.id, signal),
                    (file) => dispatch({ type: 'exported', toast: { kind: 'exported', name: row.name, fileName: file.fileName } }),
                    exportFailed,
                );
            },
            exportAll(): void {
                writes.run(
                    'export',
                    (signal) => downloads.all(signal),
                    (file) => dispatch({ type: 'exported', toast: { kind: 'exportedAll', count: file.count, fileName: file.fileName } }),
                    exportFailed,
                );
            },
            imported(at: number | null, listing: Listing, count: number, fileName: string): void {
                dispatch({ type: 'imported', store: at, listing, count, fileName });
            },
            dismissToast(): void {
                dispatch({ type: 'toastDismissed' });
            },
        };
    }, [state, endpoints, downloads, keys, logger, reads, writes]);
}
