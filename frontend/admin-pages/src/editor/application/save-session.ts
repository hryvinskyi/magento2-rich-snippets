/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Saving the draft, and the message the save leaves.
 *
 * - A draft without a type is not sent: the type is marked as wrong instead. Nothing is sent either while a save is on
 *   its way, or while the Code tab holds text that is not a template.
 * - A saved snippet is sent with the update time of the version the draft was made from; someone else's save since
 *   then is answered as a conflict, and the changes stay in the editor.
 * - A new snippet carries an idempotency key taken when the admin first asks to create it and kept until the snippet
 *   is created, so a save repeated after a failure — or one the server did receive although no answer came — creates
 *   it once.
 * - The snippet as stored becomes the saved one (`markSaved`), keeping any change made while the save was on its way.
 * - The message is one of: saved (what the new version reaches), refused (the server's first reason), conflict (the
 *   server's sentence), failed (no answer). Any change of the draft after it takes it away.
 *
 * A request the server could not answer is the "failed" message; anything else that goes wrong is reported as a
 * fault too.
 */
import { isRequestError } from '@hryvinskyi/admin-ui';
import type { Draft } from '../domain/model.ts';
import { savedReach, type SavedReach } from '../domain/save-view.ts';
import type { EditorStore } from './editor-store.ts';
import type { DraftGateway, IdempotencyKeys, SaveAnswer } from './ports.ts';

export type SaveState =
    | { readonly kind: 'idle' }
    | { readonly kind: 'saving' }
    | { readonly kind: 'saved'; readonly name: string; readonly reach: SavedReach }
    | { readonly kind: 'refused'; readonly message: string }
    | { readonly kind: 'conflict'; readonly message: string }
    | { readonly kind: 'failed' };

export interface SaveSession {
    state(): SaveState;
    /** Calls `listener` after every change of the state; answers the function that stops it. */
    subscribe(listener: () => void): () => void;
    save(): Promise<void>;
    /** Takes the message away. */
    dismiss(): void;
    /** Stops following the store and aborts a save on its way. */
    stop(): void;
}

export interface SaveDependencies {
    readonly store: EditorStore;
    readonly gateway: DraftGateway;
    readonly keys: IdempotencyKeys;
    /** Told once a new snippet has been created, with the snippet as stored. */
    readonly created: (draft: Draft) => void;
    readonly report: (error: unknown) => void;
}

const IDLE: SaveState = Object.freeze({ kind: 'idle' });
const SAVING: SaveState = Object.freeze({ kind: 'saving' });
const FAILED: SaveState = Object.freeze({ kind: 'failed' });

export function createSaveSession(dependencies: SaveDependencies): SaveSession {
    const { store } = dependencies;
    const listeners = new Set<() => void>();
    let state: SaveState = IDLE;
    let pendingKey: string | null = null;
    /** The draft the message was left for; a different draft takes the message away. */
    let messageDraft: Draft | null = null;
    let inFlight: AbortController | null = null;

    const set = (next: SaveState): void => {
        state = next;
        messageDraft = next.kind === 'idle' || next.kind === 'saving' ? null : store.state().draft;
        listeners.forEach((listener) => listener());
    };

    const stopFollowing = store.subscribe(() => {
        if (messageDraft !== null && store.state().draft !== messageDraft) {
            set(IDLE);
        }
    });

    const keyFor = (draft: Draft): string | null => {
        if (draft.id !== null) {
            return null;
        }
        pendingKey ??= dependencies.keys.next();
        return pendingKey;
    };

    const answers: { readonly [K in SaveAnswer['kind']]: (sent: Draft, answer: Extract<SaveAnswer, { readonly kind: K }>) => void } = {
        saved: (sent, answer) => {
            pendingKey = null;
            store.markSaved(answer.draft, sent);
            if (sent.id === null) {
                dependencies.created(answer.draft);
            }
            set({ kind: 'saved', name: answer.draft.name, reach: savedReach(answer.draft) });
        },
        conflict: (_sent, answer) => set({ kind: 'conflict', message: answer.message }),
        refused: (_sent, answer) => {
            if (answer.problems.some((problem) => problem.part === 'type')) {
                store.showTypeError(true);
            }
            set({ kind: 'refused', message: answer.problems[0]?.message ?? '' });
        },
    };

    const answered = <K extends SaveAnswer['kind']>(sent: Draft, answer: Extract<SaveAnswer, { readonly kind: K }>): void =>
        answers[answer.kind](sent, answer);

    return Object.freeze({
        state: (): SaveState => state,
        subscribe(listener: () => void): () => void {
            listeners.add(listener);
            return () => {
                listeners.delete(listener);
            };
        },
        async save(): Promise<void> {
            const current = store.state();
            if (state.kind === 'saving' || current.codeInvalid) {
                return;
            }
            if (current.draft.type.trim() === '') {
                store.showTypeError(true);
                return;
            }
            const sent = current.draft;
            const controller = new AbortController();
            inFlight = controller;
            set(SAVING);
            try {
                answered(sent, await dependencies.gateway.save(sent, current.saved.updatedAt, keyFor(sent), controller.signal));
            } catch (error: unknown) {
                if (controller.signal.aborted) {
                    return;
                }
                if (!isRequestError(error)) {
                    dependencies.report(error);
                }
                set(FAILED);
            } finally {
                if (inFlight === controller) {
                    inFlight = null;
                }
            }
        },
        dismiss(): void {
            if (state.kind !== 'saving') {
                set(IDLE);
            }
        },
        stop(): void {
            stopFollowing();
            inFlight?.abort();
            inFlight = null;
        },
    });
}
