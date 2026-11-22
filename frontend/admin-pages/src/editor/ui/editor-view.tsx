/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the editor's page has read and the parts it draws for other parts of the editor share with them: the sample
 * pages of the snippet's kinds, the field catalog for the page in the preview, and the store view the editor reads
 * in. The page provides it around everything it draws; reading it outside fails loudly, as a part drawn without the
 * page is a wiring fault.
 */
import { createContext, type ComponentChildren, type JSX } from 'preact';
import { useContext } from 'preact/hooks';
import type { Remote } from '../application/use-editor.ts';
import type { FieldCatalog, Sample } from '../domain/model.ts';

export interface EditorView {
    readonly samples: Remote<readonly Sample[]>;
    readonly catalog: FieldCatalog | null;
    /** The store view the editor reads in; null for the default one. */
    readonly store: number | null;
}

const ViewContext = createContext<EditorView | null>(null);

export function EditorViewProvider({ view, children }: { readonly view: EditorView; readonly children: ComponentChildren }): JSX.Element {
    return <ViewContext.Provider value={view}>{children}</ViewContext.Provider>;
}

export class MissingEditorViewError extends Error {
    constructor() {
        super('A part of the snippet editor was drawn outside the editor\'s page.');
        this.name = 'MissingEditorViewError';
    }
}

export function useEditorView(): EditorView {
    const view = useContext(ViewContext);
    if (view === null) {
        throw new MissingEditorViewError();
    }
    return view;
}
