/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The hook binding the draft's previews to a component: the sample page picked (the last previewed one while it is
 * still among the samples, else the first), a preview asked for at once when the sample or the store view changes and
 * after a pause when the draft changes in anything the output depends on, each answer shown through the editor's
 * store, and where the previews stand. Leaving the component drops what is waiting and aborts what is on its way.
 */
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import type { Draft, Sample } from '../domain/model.ts';
import { chosenSample } from '../domain/preview-view.ts';
import type { EditorStore } from './editor-store.ts';
import type { DraftGateway, Scheduler } from './ports.ts';
import { createPreviewRequests, PREVIEW_PAUSE_MS, type PreviewStatus } from './preview-requests.ts';
import { useEditorState } from './use-editor.ts';

/** Where the previews stand; `none` when there is no sample page to preview. */
export type PreviewStanding = PreviewStatus | 'none';

export interface PreviewControls {
    readonly standing: PreviewStanding;
    readonly sample: Sample | null;
    pick(ref: string): void;
    retry(): void;
}

/** What of the draft the output depends on; its name, switch and saved version do not change it. */
const outputKey = (draft: Draft): string =>
    JSON.stringify([draft.id, draft.template, draft.isBlock, draft.pageKinds, draft.conditions, draft.storeIds]);

export function usePreview(
    store: EditorStore,
    ports: { readonly drafts: DraftGateway; readonly scheduler: Scheduler },
    samples: readonly Sample[],
    readIn: number | null,
    report: (error: unknown) => void,
): PreviewControls {
    const { draft, preview } = useEditorState(store);
    const [wanted, setWanted] = useState<string | null>(preview?.sample.ref ?? null);
    const [standing, setStanding] = useState<PreviewStanding>(samples.length === 0 ? 'none' : 'waiting');
    const requests = useMemo(() => createPreviewRequests({
        gateway: ports.drafts,
        scheduler: ports.scheduler,
        pauseMs: PREVIEW_PAUSE_MS,
        show: (shown) => store.showPreview(shown),
        status: setStanding,
        report,
    }), [store, ports, report]);
    useEffect(() => () => requests.stop(), [requests]);

    const sample = chosenSample(samples, wanted);
    const key = outputKey(draft);
    const asked = useRef<{ readonly sample: string; readonly readIn: number | null; readonly key: string } | null>(null);
    useEffect(() => {
        if (sample === null) {
            requests.stop();
            asked.current = null;
            setStanding('none');
            return;
        }
        const before = asked.current;
        asked.current = { sample: sample.ref, readIn, key };
        const what = { draft: store.state().draft, sample, store: readIn };
        if (before === null || before.sample !== sample.ref || before.readIn !== readIn) {
            requests.now(what);
        } else if (before.key !== key) {
            requests.ask(what);
        }
    }, [requests, store, sample, readIn, key]);

    return {
        standing,
        sample,
        pick: (ref: string): void => setWanted(ref),
        retry: (): void => {
            if (sample !== null) {
                requests.now({ draft: store.state().draft, sample, store: readIn });
            }
        },
    };
}
