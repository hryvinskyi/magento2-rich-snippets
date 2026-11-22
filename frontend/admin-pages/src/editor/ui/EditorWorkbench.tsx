/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The whole snippet editor: the editor's page with what the rest of the editor draws into it — the choice between the
 * builder and the code (the builder cannot be chosen while the code is not a template), the Code tab in place of the
 * builder, the Preview and Checks panels beside the cards, and the message a save leaves — and the work its header
 * only announces: Save, performed by the save session, and Test, which opens the page test on the previewed page.
 *
 * A preview runs the draft against a catalog page, so the Preview and Checks panels are drawn only for an admin who may
 * change snippets; for an admin who may only see the module the cards take the whole width and nothing is previewed.
 *
 * A snippet created by a save is shown at its own editor's address from then on, so a reload opens it.
 */
import type { JSX } from 'preact';
import { useCallback, useEffect, useState } from 'preact/hooks';
import { linkTo, SegmentedControl, usePage, withParam } from '@hryvinskyi/admin-ui';
import type { EditorDocument } from '../adapters/editor-document.ts';
import type { EditorStore } from '../application/editor-store.ts';
import { useEditorState } from '../application/use-editor.ts';
import { useSave } from '../application/use-save.ts';
import type { Draft } from '../domain/model.ts';
import { CodeCard } from './CodeCard.tsx';
import type { EditorPorts } from './editor-env.tsx';
import { EditorIsland } from './EditorIsland.tsx';
import { SaveNotice } from './SaveNotice.tsx';
import { SidePanels } from './SidePanels.tsx';

const EDITOR_ROUTE = 'hryvinskyi_richsnippets/snippets/edit';
const TEST_ROUTE = 'hryvinskyi_richsnippets/test/index';

export type EditMode = 'builder' | 'code';

/** The Builder | Code choice at the right end of the Properties card's head. */
export function EditAs({ mode, codeInvalid, onChange }: {
    readonly mode: EditMode;
    readonly codeInvalid: boolean;
    readonly onChange: (mode: EditMode) => void;
}): JSX.Element {
    const { translator: { t } } = usePage();
    return (
        <SegmentedControl
            label={t('editor.editAs.label')}
            value={mode}
            options={[
                { value: 'builder', label: t('editor.editAs.builder'), icon: 'account_tree', disabled: codeInvalid },
                { value: 'code', label: t('editor.editAs.code'), icon: 'code' },
            ]}
            onChange={(value) => onChange(value === 'code' ? 'code' : 'builder')}
        />
    );
}

export interface EditorWorkbenchProps {
    readonly document: EditorDocument;
    readonly store: EditorStore;
    readonly ports: EditorPorts;
}

export function EditorWorkbench({ document, store, ports }: EditorWorkbenchProps): JSX.Element {
    const [mode, setMode] = useState<EditMode>('builder');
    const { codeInvalid } = useEditorState(store);
    const report = useCallback((error: unknown): void => ports.logger.error('The snippet editor failed.', error), [ports]);
    const created = useCallback((draft: Draft): void => {
        const address = draft.id === null ? null : document.link(withParam(linkTo(EDITOR_ROUTE), 'id', String(draft.id)));
        if (address !== null) {
            ports.navigation.replace(address);
        }
    }, [document, ports]);
    const save = useSave(store, ports, created, report);

    useEffect(() => store.on('test', () => {
        const sample = store.state().preview?.sample ?? null;
        const test = linkTo(TEST_ROUTE);
        const address = document.link(sample === null ? test : withParam(test, 'page', sample.ref));
        if (address !== null) {
            ports.navigation.go(address);
        }
    }), [store, document, ports]);

    const editAs = <EditAs mode={mode} codeInvalid={codeInvalid} onChange={setMode} />;
    return (
        <EditorIsland
            document={document}
            store={store}
            ports={ports}
            slots={{
                aside: document.allows('snippets') ? <SidePanels /> : undefined,
                propertiesAside: editAs,
                propertiesView: mode === 'code' ? <CodeCard aside={editAs} /> : undefined,
                notice: <SaveNotice state={save.state} actions={{ retry: save.save, reload: ports.navigation.reload, dismiss: save.dismiss }} />,
                saving: save.state.kind === 'saving',
            }}
        />
    );
}
