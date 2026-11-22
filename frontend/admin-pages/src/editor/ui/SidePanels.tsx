/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The editor's side column: the Preview panel and the Checks panel, both about the draft on one sample page in the
 * store view the editor reads in. The previews follow the draft (see usePreview); the panels draw what the server
 * answered and what it says about it.
 */
import type { JSX } from 'preact';
import { useEditorState } from '../application/use-editor.ts';
import { usePreview } from '../application/use-preview.ts';
import { ChecksPanel } from './ChecksPanel.tsx';
import { useEditorEnv } from './editor-env.tsx';
import { useEditorView } from './editor-view.tsx';
import { PreviewPanel } from './PreviewPanel.tsx';

export function SidePanels(): JSX.Element {
    const { store, ports, report } = useEditorEnv();
    const { samples, store: readIn } = useEditorView();
    const listed = samples.kind === 'done' ? samples.value : [];
    const controls = usePreview(store, ports, listed, readIn, report);
    const { preview } = useEditorState(store);
    return (
        <>
            <PreviewPanel controls={controls} samples={listed} loadingSamples={samples.kind === 'loading'} />
            <ChecksPanel rows={preview?.checks ?? null} />
        </>
    );
}
