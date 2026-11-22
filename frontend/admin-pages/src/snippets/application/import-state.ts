/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The import drawer's steps: closed; open with no file; reading the file the admin chose and asking the server what
 * importing it would do; the file refused as a whole (or the question failed); and the preview, one choice per
 * snippet, with the import on its way or failed.
 *
 * The preview carries the key of the admin's intent: a new one each time the preview arrives and each time a choice
 * changes, so an import repeated after a timeout with the same choices imports nothing more, and an import with other
 * choices is a write of its own. Choosing another file starts again from reading it. Every event has its own handler
 * in an exhaustive map.
 */
import type { RequestFailure } from '@hryvinskyi/admin-ui';
import type { ImportChoices } from '../domain/import-view.ts';
import { defaultChoices } from '../domain/import-view.ts';
import type { ImportChoice, ImportPreview } from '../domain/model.ts';

/** The file the admin chose, as the drawer shows it, with its text once read. */
export interface ReadFile {
    readonly name: string;
    readonly size: number;
    readonly text: string;
}

interface Steps {
    closed: Record<never, never>;
    choosing: Record<never, never>;
    reading: { readonly name: string; readonly size: number };
    refused: { readonly name: string; readonly size: number; readonly failure: RequestFailure };
    preview: {
        readonly file: ReadFile;
        readonly preview: ImportPreview;
        readonly choices: ImportChoices;
        readonly idempotencyKey: string;
        readonly committing: boolean;
        readonly failure: RequestFailure | null;
    };
}

export type ImportStep = keyof Steps;

export type ImportState<K extends ImportStep = ImportStep> = { [P in K]: { readonly step: P } & Steps[P] }[K];

interface ImportEvents {
    opened: Record<never, never>;
    closed: Record<never, never>;
    fileChosen: { readonly name: string; readonly size: number };
    previewed: { readonly file: ReadFile; readonly preview: ImportPreview; readonly idempotencyKey: string };
    refused: { readonly name: string; readonly size: number; readonly failure: RequestFailure };
    choiceChanged: { readonly code: string; readonly choice: ImportChoice; readonly idempotencyKey: string };
    commitStarted: Record<never, never>;
    commitFailed: { readonly failure: RequestFailure };
}

export type ImportEventKind = keyof ImportEvents;

export type ImportEvent<K extends ImportEventKind = ImportEventKind> =
    { [P in K]: { readonly type: P } & ImportEvents[P] }[K];

const CLOSED: ImportState<'closed'> = Object.freeze({ step: 'closed' });

/** The preview's step with a change, or the state unchanged when the drawer is not on its preview. */
function onPreview(state: ImportState, change: (preview: ImportState<'preview'>) => ImportState<'preview'>): ImportState {
    return state.step === 'preview' ? change(state) : state;
}

const handlers: { readonly [K in ImportEventKind]: (state: ImportState, event: ImportEvent<K>) => ImportState } = {
    opened: () => ({ step: 'choosing' }),
    closed: () => CLOSED,
    fileChosen: (_state, event) => ({ step: 'reading', name: event.name, size: event.size }),
    previewed: (_state, event) => ({
        step: 'preview',
        file: event.file,
        preview: event.preview,
        choices: defaultChoices(event.preview),
        idempotencyKey: event.idempotencyKey,
        committing: false,
        failure: null,
    }),
    refused: (_state, event) => ({ step: 'refused', name: event.name, size: event.size, failure: event.failure }),
    choiceChanged: (state, event) => onPreview(state, (preview) => ({
        ...preview,
        choices: new Map(preview.choices).set(event.code, event.choice),
        idempotencyKey: event.idempotencyKey,
        failure: null,
    })),
    commitStarted: (state) => onPreview(state, (preview) => ({ ...preview, committing: true, failure: null })),
    commitFailed: (state, event) => onPreview(state, (preview) => ({ ...preview, committing: false, failure: event.failure })),
};

export function reduceImport<K extends ImportEventKind>(state: ImportState, event: ImportEvent<K>): ImportState {
    return handlers[event.type](state, event);
}

/** The reducer the drawer's hook holds: every event of the drawer. */
export const importReducer = (state: ImportState, event: ImportEvent): ImportState => reduceImport(state, event);

export const initialImport = (): ImportState => CLOSED;
