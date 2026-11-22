/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the import drawer to its endpoints. Choosing a file reads it and asks what importing it would do; choosing
 * another file while the first is still being asked about replaces it. The import sends the file's text again — the
 * server reads and checks it anew — with the choices and the key of the admin's intent, and hands the list it answers
 * to the page. Closing the drawer or leaving the page aborts what is in flight.
 */
import { useEffect, useMemo, useReducer } from 'preact/hooks';
import { createLatestRequest, settle, type Logger } from '@hryvinskyi/admin-ui';
import { importedCount } from '../domain/import-view.ts';
import type { ImportChoice, Listing } from '../domain/model.ts';
import { importReducer, initialImport, type ImportState } from './import-state.ts';
import type { ChosenFile, IdempotencyKeys, SnippetsEndpoints } from './ports.ts';

export interface ImportControls {
    readonly state: ImportState;
    open(): void;
    close(): void;
    choose(file: ChosenFile): void;
    pick(code: string, choice: ImportChoice): void;
    commit(): void;
}

/** Told the list an import answered, the store view it is of, how many snippets it added or replaced, and the file. */
export type ImportDone = (store: number | null, listing: Listing, count: number, fileName: string) => void;

export function useImport(
    endpoints: SnippetsEndpoints,
    keys: IdempotencyKeys,
    logger: Logger,
    store: number | null,
    done: ImportDone,
): ImportControls {
    const [state, dispatch] = useReducer(importReducer, undefined, initialImport);
    const request = useMemo(
        () => createLatestRequest((error: unknown): void => logger.error('The import file could not be read or imported.', error)),
        [logger],
    );
    useEffect(() => () => request.cancel(), [request]);

    return useMemo(() => ({
        state,
        open(): void {
            dispatch({ type: 'opened' });
        },
        close(): void {
            request.cancel();
            dispatch({ type: 'closed' });
        },
        choose(file: ChosenFile): void {
            dispatch({ type: 'fileChosen', name: file.name, size: file.size });
            void request.run(async (signal) => {
                const text = await file.text();
                return { text, preview: await endpoints.previewImport(text, signal) };
            }).then((outcome) => settle(outcome, {
                done: ({ text, preview }) => dispatch({
                    type: 'previewed',
                    file: { name: file.name, size: file.size, text },
                    preview,
                    idempotencyKey: keys.next(),
                }),
                failed: (failure) => dispatch({ type: 'refused', name: file.name, size: file.size, failure }),
            }));
        },
        pick(code: string, choice: ImportChoice): void {
            dispatch({ type: 'choiceChanged', code, choice, idempotencyKey: keys.next() });
        },
        commit(): void {
            if (state.step !== 'preview' || state.committing || importedCount(state.choices) === 0) {
                return;
            }
            const { file, choices, idempotencyKey } = state;
            dispatch({ type: 'commitStarted' });
            void request.run((signal) => endpoints.commitImport(file.text, choices, idempotencyKey, store, signal))
                .then((outcome) => settle(outcome, {
                    done: (answer) => {
                        dispatch({ type: 'closed' });
                        done(store, answer.list, answer.imported, file.name);
                    },
                    failed: (failure) => dispatch({ type: 'commitFailed', failure }),
                }));
        },
    }), [state, endpoints, keys, store, done, request]);
}
