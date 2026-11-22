/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Library page shows, and how it changes: the library last read and whether a newer one is on its way, the
 * search and the group chip, the add drawer with its questions and the answers chosen, the recommended set on its
 * way, and the message about the last add.
 *
 * - Typing a search resets the chip to every group; clearing it does too.
 * - A page printed without its library starts as a failed read. *Try again* reads the library anew; until it
 *   arrives the card shows it is loading; a failure keeps the last library and lets the card say so.
 * - Opening the drawer of a template with questions reads its answers; until they arrive the questions show they are
 *   loading, a failure says the suggested answers are taken. An answer read for a drawer no longer open is dropped.
 * - An add carries the key generated when the drawer opened, so pressing Add again after a failure repeats the same
 *   add rather than making a second copy. Its answer replaces the library and closes the drawer with a message; a
 *   refusal keeps the drawer open and says why; a failure keeps it open and says so.
 * - The recommended set keeps its key until it succeeds, for the same reason; its answer replaces the library.
 *
 * Every event has its own handler in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import type { GroupFilter } from '../domain/filters.ts';
import type { AddAnswer, Library, LibraryTemplate, TemplateAnswers } from '../domain/model.ts';

/**
 * The library on screen: shown, being read anew, or that read failed — its failure null when the server could not
 * read the library as it printed the page.
 */
export type ListState =
    | { readonly status: 'ready'; readonly library: Library }
    | { readonly status: 'loading'; readonly library: Library }
    | { readonly status: 'failed'; readonly library: Library; readonly failure: RequestFailure | null };

/** The questions of the template in the drawer: it asks none, they load, they are in, or they could not be read. */
export type QuestionsState =
    | { readonly status: 'none' }
    | { readonly status: 'loading' }
    | { readonly status: 'ready'; readonly answers: TemplateAnswers }
    | { readonly status: 'failed'; readonly failure: RequestFailure };

/** The add drawer of one template. */
export interface Drawer {
    readonly code: string;
    readonly questions: QuestionsState;
    /** The answer key the admin chose for each question, by question code. */
    readonly chosen: ReadonlyMap<string, string>;
    /** The idempotency key of this intent to add. */
    readonly key: string;
    readonly adding: boolean;
    readonly failure: RequestFailure | null;
    /** Why the add was refused; null until it is. */
    readonly refused: string | null;
}

interface Toasts {
    /** One template was added. */
    added: { readonly answer: AddAnswer; readonly template: LibraryTemplate };
    /** The recommended set was added. */
    set: { readonly answer: AddAnswer };
    /** The recommended set could not be added. */
    failed: { readonly failure: RequestFailure };
}

export type ToastKind = keyof Toasts;

export type Toast<K extends ToastKind = ToastKind> = { [P in K]: { readonly kind: P } & Toasts[P] }[K];

export interface LibraryState {
    readonly list: ListState;
    readonly query: string;
    readonly group: GroupFilter;
    readonly drawer: Drawer | null;
    /** The idempotency key of the recommended set while it is on its way or failed; null otherwise. */
    readonly setKey: string | null;
    readonly settingUp: boolean;
    readonly toast: Toast | null;
}

interface LibraryEvents {
    searched: { readonly query: string };
    groupChosen: { readonly group: GroupFilter };
    retried: Record<never, never>;
    listLoaded: { readonly library: Library };
    listFailed: { readonly failure: RequestFailure };
    drawerOpened: { readonly code: string; readonly key: string; readonly asks: boolean };
    drawerClosed: Record<never, never>;
    questionsRetried: Record<never, never>;
    questionsLoaded: { readonly code: string; readonly answers: TemplateAnswers };
    questionsFailed: { readonly code: string; readonly failure: RequestFailure };
    answerChosen: { readonly question: string; readonly answer: string };
    addStarted: Record<never, never>;
    addAnswered: { readonly code: string; readonly answer: AddAnswer };
    addFailed: { readonly code: string; readonly failure: RequestFailure };
    setStarted: { readonly key: string };
    setAnswered: { readonly answer: AddAnswer };
    setFailed: { readonly failure: RequestFailure };
    toastDismissed: Record<never, never>;
}

export type LibraryEventKind = keyof LibraryEvents;

export type LibraryEvent<K extends LibraryEventKind = LibraryEventKind> =
    { [P in K]: { readonly type: P } & LibraryEvents[P] }[K];

/** The drawer changed by `change` while it is still the one of `code`; any other state is left as it is. */
function inDrawer(state: LibraryState, code: string, change: (drawer: Drawer) => Partial<LibraryState>): LibraryState {
    return state.drawer !== null && state.drawer.code === code ? { ...state, ...change(state.drawer) } : state;
}

const handlers: { readonly [K in LibraryEventKind]: (state: LibraryState, event: LibraryEvent<K>) => LibraryState } = {
    searched: (state, event) => ({ ...state, query: event.query, group: null }),
    groupChosen: (state, event) => ({ ...state, group: event.group }),
    retried: (state) => ({ ...state, list: { status: 'loading', library: state.list.library } }),
    listLoaded: (state, event) => ({ ...state, list: { status: 'ready', library: event.library } }),
    listFailed: (state, event) => ({ ...state, list: { status: 'failed', library: state.list.library, failure: event.failure } }),
    drawerOpened: (state, event) => ({
        ...state,
        drawer: {
            code: event.code,
            questions: event.asks ? { status: 'loading' } : { status: 'none' },
            chosen: new Map(),
            key: event.key,
            adding: false,
            failure: null,
            refused: null,
        },
        toast: null,
    }),
    drawerClosed: (state) => ({ ...state, drawer: null }),
    questionsRetried: (state) => (state.drawer === null
        ? state
        : { ...state, drawer: { ...state.drawer, questions: { status: 'loading' } } }),
    questionsLoaded: (state, event) => inDrawer(state, event.code, (drawer) => ({
        drawer: { ...drawer, questions: { status: 'ready', answers: event.answers } },
    })),
    questionsFailed: (state, event) => inDrawer(state, event.code, (drawer) => ({
        drawer: { ...drawer, questions: { status: 'failed', failure: event.failure } },
    })),
    answerChosen: (state, event) => (state.drawer === null
        ? state
        : { ...state, drawer: { ...state.drawer, chosen: new Map(state.drawer.chosen).set(event.question, event.answer) } }),
    addStarted: (state) => (state.drawer === null
        ? state
        : { ...state, drawer: { ...state.drawer, adding: true, failure: null, refused: null } }),
    addAnswered: (state, event) => {
        const next: LibraryState = { ...state, list: { status: 'ready', library: event.answer.library } };
        const refused = event.answer.refused;
        if (refused !== null) {
            return inDrawer(next, event.code, (drawer) => ({ drawer: { ...drawer, adding: false, refused: refused.reason } }));
        }
        const template = state.list.library.templates.find((candidate) => candidate.code === event.code);
        return {
            ...next,
            drawer: null,
            toast: template === undefined ? null : { kind: 'added', answer: event.answer, template },
        };
    },
    addFailed: (state, event) => inDrawer(state, event.code, (drawer) => ({
        drawer: { ...drawer, adding: false, failure: event.failure },
    })),
    setStarted: (state, event) => ({ ...state, setKey: event.key, settingUp: true, toast: null }),
    setAnswered: (state, event) => ({
        ...state,
        list: { status: 'ready', library: event.answer.library },
        setKey: null,
        settingUp: false,
        toast: { kind: 'set', answer: event.answer },
    }),
    setFailed: (state, event) => ({ ...state, settingUp: false, toast: { kind: 'failed', failure: event.failure } }),
    toastDismissed: (state) => ({ ...state, toast: null }),
};

export function reduceLibrary<K extends LibraryEventKind>(state: LibraryState, event: LibraryEvent<K>): LibraryState {
    return handlers[event.type](state, event);
}

/** The reducer the page's hook holds: every event of the page. */
export const libraryReducer = (state: LibraryState, event: LibraryEvent): LibraryState => reduceLibrary(state, event);

/**
 * The page as printed: the library already in — or failed, when the server could not read it — no search, every
 * group, nothing open.
 */
export const initialLibrary = (library: Library, unavailable = false): LibraryState => ({
    list: unavailable ? { status: 'failed', library, failure: null } : { status: 'ready', library },
    query: '',
    group: null,
    drawer: null,
    setKey: null,
    settingUp: false,
    toast: null,
});
