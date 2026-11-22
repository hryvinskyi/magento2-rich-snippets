/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Binds the library's state to its endpoints.
 *
 * - *Try again* reads the library; only the newest read counts.
 * - Opening a drawer reads the template's questions, aborting the read of a drawer opened before; the drawer the
 *   address names opens once the page is drawn.
 * - An add posts the answers chosen (the suggested one for a question left alone) with the drawer's key; the
 *   recommended set posts its own key, generated when it is first pressed and kept until it succeeds.
 * - Leaving the page aborts what is in flight, and an answer that arrives after that is not shown.
 */
import { useEffect, useMemo, useReducer } from 'preact/hooks';
import { createLatestRequest, settle, type Logger } from '@hryvinskyi/admin-ui';
import { answersToSend } from '../domain/add-view.ts';
import type { GroupFilter } from '../domain/filters.ts';
import type { LibraryData, LibraryTemplate } from '../domain/model.ts';
import { initialLibrary, libraryReducer, type LibraryState } from './library-state.ts';
import type { IdempotencyKeys, LibraryEndpoints } from './ports.ts';

export interface LibraryPorts {
    readonly endpoints: LibraryEndpoints;
    readonly keys: IdempotencyKeys;
    readonly logger: Logger;
}

export interface LibraryControls {
    readonly state: LibraryState;
    search(query: string): void;
    chooseGroup(group: GroupFilter): void;
    retry(): void;
    open(template: LibraryTemplate): void;
    close(): void;
    retryQuestions(): void;
    choose(question: string, answer: string): void;
    add(): void;
    addRecommended(): void;
    dismissToast(): void;
}

/**
 * @param printed the library as the page was printed with it, and whether the server could read it
 * @param opening the code of the template whose drawer opens once the page is drawn; null for none
 */
export function useLibrary(ports: LibraryPorts, printed: LibraryData, opening: string | null): LibraryControls {
    const { endpoints, keys, logger } = ports;
    const first = printed.library;
    const [state, dispatch] = useReducer(libraryReducer, printed, (data) => initialLibrary(data.library, data.unavailable));
    const requests = useMemo(() => ({
        list: createLatestRequest((error: unknown): void => logger.error('The library could not be read.', error)),
        questions: createLatestRequest((error: unknown): void => logger.error('The suggested answers could not be read.', error)),
        add: createLatestRequest((error: unknown): void => logger.error('The template could not be added.', error)),
        set: createLatestRequest((error: unknown): void => logger.error('The recommended set could not be added.', error)),
    }), [logger]);
    useEffect(() => () => Object.values(requests).forEach((request) => request.cancel()), [requests]);

    const loadQuestions = useMemo(() => (code: string): void => {
        void requests.questions.run((signal) => endpoints.suggestions(code, signal)).then((outcome) => settle(outcome, {
            done: (answers) => dispatch({ type: 'questionsLoaded', code, answers }),
            failed: (failure) => dispatch({ type: 'questionsFailed', code, failure }),
        }));
    }, [requests, endpoints]);

    const open = useMemo(() => (template: LibraryTemplate): void => {
        const asks = template.questions.length > 0;
        dispatch({ type: 'drawerOpened', code: template.code, key: keys.next(), asks });
        if (asks) {
            loadQuestions(template.code);
        } else {
            requests.questions.cancel();
        }
    }, [keys, loadQuestions, requests]);

    useEffect(() => {
        const template = first.templates.find((candidate) => candidate.code === opening);
        if (template !== undefined) {
            open(template);
        }
    }, [first, opening, open]);

    return useMemo(() => ({
        state,
        search(query: string): void {
            dispatch({ type: 'searched', query });
        },
        chooseGroup(group: GroupFilter): void {
            dispatch({ type: 'groupChosen', group });
        },
        retry(): void {
            dispatch({ type: 'retried' });
            void requests.list.run((signal) => endpoints.list(signal)).then((outcome) => settle(outcome, {
                done: (library) => dispatch({ type: 'listLoaded', library }),
                failed: (failure) => dispatch({ type: 'listFailed', failure }),
            }));
        },
        open,
        close(): void {
            requests.questions.cancel();
            dispatch({ type: 'drawerClosed' });
        },
        retryQuestions(): void {
            if (state.drawer !== null) {
                dispatch({ type: 'questionsRetried' });
                loadQuestions(state.drawer.code);
            }
        },
        choose(question: string, answer: string): void {
            dispatch({ type: 'answerChosen', question, answer });
        },
        add(): void {
            const drawer = state.drawer;
            if (drawer === null || drawer.adding) {
                return;
            }
            const answers = drawer.questions.status === 'ready'
                ? answersToSend(drawer.questions.answers.questions, drawer.chosen)
                : new Map<string, string>();
            const { code, key } = drawer;
            dispatch({ type: 'addStarted' });
            void requests.add.run((signal) => endpoints.add(code, answers, key, signal)).then((outcome) => settle(outcome, {
                done: (answer) => dispatch({ type: 'addAnswered', code, answer }),
                failed: (failure) => dispatch({ type: 'addFailed', code, failure }),
            }));
        },
        addRecommended(): void {
            if (state.settingUp) {
                return;
            }
            const key = state.setKey ?? keys.next();
            dispatch({ type: 'setStarted', key });
            void requests.set.run((signal) => endpoints.addRecommended(key, signal)).then((outcome) => settle(outcome, {
                done: (answer) => dispatch({ type: 'setAnswered', answer }),
                failed: (failure) => dispatch({ type: 'setFailed', failure }),
            }));
        },
        dismissToast(): void {
            dispatch({ type: 'toastDismissed' });
        },
    }), [state, endpoints, keys, requests, open, loadQuestions]);
}
