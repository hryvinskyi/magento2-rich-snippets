/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor with what its components read: the page's wording in the admin's locale (the kit's), the
 * addresses of the screens the admin may open, the editor's store, its ports, and the document's first data.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { createPageTranslator, PageProvider, useKit } from '@hryvinskyi/admin-ui';
import type { EditorDocument } from '../adapters/editor-document.ts';
import type { EditorStore } from '../application/editor-store.ts';
import { EditorEnvProvider, type EditorEnv, type EditorPorts } from './editor-env.tsx';
import { EditorPage, type EditorSlots } from './EditorPage.tsx';

export interface EditorIslandProps {
    readonly document: EditorDocument;
    readonly store: EditorStore;
    readonly ports: EditorPorts;
    readonly slots?: EditorSlots | undefined;
}

export function EditorIsland({ document, store, ports, slots }: EditorIslandProps): JSX.Element {
    const { locale } = useKit();
    const page = useMemo(() => ({
        translator: createPageTranslator(locale, document.phrases, ports.logger),
        link: document.link,
    }), [locale, document, ports.logger]);
    const env = useMemo((): EditorEnv => ({
        store,
        ports,
        kinds: document.data.kinds,
        kindsByCode: new Map(document.data.kinds.map((kind) => [kind.code, kind])),
        conditions: document.data.conditions,
        defaults: document.data.defaults,
        typeSuggestions: document.data.typeSuggestions,
        stores: document.stores,
        usedIn: document.data.usedIn,
        isNew: document.data.isNew,
        section: document.data.section,
        canChange: document.allows('snippets'),
        canTest: document.allows('test'),
        report: (error: unknown): void => ports.logger.error('The snippet editor failed.', error),
    }), [document, store, ports]);
    return (
        <PageProvider page={page}>
            <EditorEnvProvider env={env}>
                <EditorPage shell={document.shell} slots={slots} />
            </EditorEnvProvider>
        </PageProvider>
    );
}
