/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The previews of the draft as it changes. Each change asks for a preview after a short wait (`ask`); another change
 * within the wait asks again instead, so typing sends one request when it pauses. A change aborts the request still on
 * its way for the draft before it, and only the latest request's answer is shown: the answer to an older draft never
 * replaces a newer one. Picking another sample or trying again asks at once (`now`).
 *
 * Where the previews stand is told as it changes: waiting for the pause, loading, shown, or failed. A request the
 * server could not answer is a failure to show; anything else that goes wrong is also reported as a fault.
 */
import { isRequestError } from '@hryvinskyi/admin-ui';
import type { Draft, PreviewResult, Sample } from '../domain/model.ts';
import type { DraftGateway, Scheduler } from './ports.ts';

/** What a preview is made of: the draft, the sample page and the store view it is read in. */
export interface PreviewAsk {
    readonly draft: Draft;
    readonly sample: Sample;
    readonly store: number | null;
}

export type PreviewStatus = 'waiting' | 'loading' | 'shown' | 'failed';

export interface PreviewRequests {
    /** Asks for a preview once the changes pause. */
    ask(what: PreviewAsk): void;
    /** Asks for a preview at once. */
    now(what: PreviewAsk): void;
    /** Drops what is waiting and aborts what is on its way. */
    stop(): void;
}

export interface PreviewDependencies {
    readonly gateway: DraftGateway;
    readonly scheduler: Scheduler;
    /** How long changes must pause before a preview is asked for. */
    readonly pauseMs: number;
    readonly show: (preview: PreviewResult) => void;
    readonly status: (status: PreviewStatus) => void;
    readonly report: (error: unknown) => void;
}

export const PREVIEW_PAUSE_MS = 400;

export function createPreviewRequests(dependencies: PreviewDependencies): PreviewRequests {
    let cancelWait: (() => void) | null = null;
    let inFlight: AbortController | null = null;

    const dropWait = (): void => {
        cancelWait?.();
        cancelWait = null;
    };

    const abortInFlight = (): void => {
        inFlight?.abort();
        inFlight = null;
    };

    const send = (what: PreviewAsk): void => {
        dropWait();
        abortInFlight();
        const controller = new AbortController();
        inFlight = controller;
        dependencies.status('loading');
        dependencies.gateway.preview(what.draft, what.sample, what.store, controller.signal).then((preview) => {
            if (inFlight === controller) {
                inFlight = null;
                dependencies.show(preview);
                dependencies.status('shown');
            }
        }, (error: unknown) => {
            if (controller.signal.aborted || inFlight !== controller) {
                return;
            }
            inFlight = null;
            if (!isRequestError(error)) {
                dependencies.report(error);
            }
            dependencies.status('failed');
        });
    };

    return Object.freeze({
        ask(what: PreviewAsk): void {
            dropWait();
            abortInFlight();
            dependencies.status('waiting');
            cancelWait = dependencies.scheduler.after(dependencies.pauseMs, () => {
                cancelWait = null;
                send(what);
            });
        },
        now: send,
        stop(): void {
            dropWait();
            abortInFlight();
        },
    });
}
