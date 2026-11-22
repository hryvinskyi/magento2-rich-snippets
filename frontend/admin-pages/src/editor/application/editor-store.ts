/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The editor's state and the seam every part of the editor works through: the snippet as last saved, the draft the
 * admin changes, the latest preview, whether the type is shown as wrong, and whether the text in the Code tab is not a
 * template yet (which keeps the save off while the draft holds the last valid template). Each part reads the state,
 * changes the draft through it, and is told of every change; a request for an action the part asking does not
 * perform itself — save, test, opening the Pages section — is announced to whoever listens for it.
 *
 * The draft's template is its one source of properties: every change goes through `change` or `setTemplate`, which
 * keep the draft's type in step with the template's root.
 */
import { withTemplate } from '../domain/draft.ts';
import type { Draft, PreviewResult } from '../domain/model.ts';

export interface EditorState {
    readonly saved: Draft;
    readonly draft: Draft;
    readonly preview: PreviewResult | null;
    /** The server refused the type; the snippet card marks it until the type changes. */
    readonly typeError: boolean;
    /** The Code tab holds text that is not a template yet. */
    readonly codeInvalid: boolean;
}

/** What one part of the editor asks for and another performs. */
export type EditorRequest = 'save' | 'test' | 'pages';

export interface EditorStore {
    state(): EditorState;
    /** Calls `listener` after every change of the state; answers the function that stops it. */
    subscribe(listener: () => void): () => void;
    /** Changes the draft. */
    change(edit: (draft: Draft) => Draft): void;
    /** Puts a new template into the draft, as typed in code. */
    setTemplate(template: string): void;
    /**
     * Takes `saved` as the snippet saved now. The draft becomes it, so nothing is unsaved — unless the draft changed
     * since `sent`, the draft the save was made of, was sent: then the changes stay, on the saved snippet's identity
     * and version, and are still unsaved.
     */
    markSaved(saved: Draft, sent?: Draft): void;
    showPreview(preview: PreviewResult | null): void;
    showTypeError(shown: boolean): void;
    showCodeInvalid(invalid: boolean): void;
    /** Announces a request to its listeners. */
    request(request: EditorRequest): void;
    /** Calls `listener` on every announcement of `request`; answers the function that stops it. */
    on(request: EditorRequest, listener: () => void): () => void;
}

export function createEditorStore(saved: Draft): EditorStore {
    let state: EditorState = { saved, draft: saved, preview: null, typeError: false, codeInvalid: false };
    const listeners = new Set<() => void>();
    const requests: { readonly [R in EditorRequest]: Set<() => void> } = { save: new Set(), test: new Set(), pages: new Set() };
    const set = (next: EditorState): void => {
        if (next === state) {
            return;
        }
        state = next;
        listeners.forEach((listener) => listener());
    };
    const subscribeTo = (set: Set<() => void>, listener: () => void): (() => void) => {
        set.add(listener);
        return () => {
            set.delete(listener);
        };
    };
    return Object.freeze({
        state: (): EditorState => state,
        subscribe: (listener: () => void) => subscribeTo(listeners, listener),
        change(edit: (draft: Draft) => Draft): void {
            const draft = edit(state.draft);
            if (draft !== state.draft) {
                set({ ...state, draft, typeError: state.typeError && draft.type === state.draft.type });
            }
        },
        setTemplate(template: string): void {
            if (template !== state.draft.template) {
                const draft = withTemplate(state.draft, template);
                set({ ...state, draft, typeError: state.typeError && draft.type === state.draft.type });
            }
        },
        markSaved(stored: Draft, sent?: Draft): void {
            if (sent === undefined || state.draft === sent) {
                set({ ...state, saved: stored, draft: stored, typeError: false });
                return;
            }
            const { id, code, origin, updatedAt, updatedBy } = stored;
            set({ ...state, saved: stored, draft: { ...state.draft, id, code, origin, updatedAt, updatedBy } });
        },
        showPreview(preview: PreviewResult | null): void {
            set({ ...state, preview });
        },
        showTypeError(shown: boolean): void {
            set({ ...state, typeError: shown });
        },
        showCodeInvalid(invalid: boolean): void {
            if (invalid !== state.codeInvalid) {
                set({ ...state, codeInvalid: invalid });
            }
        },
        request(request: EditorRequest): void {
            [...requests[request]].forEach((listener) => listener());
        },
        on: (request: EditorRequest, listener: () => void) => subscribeTo(requests[request], listener),
    });
}
