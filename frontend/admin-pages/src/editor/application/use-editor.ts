/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The hooks binding the editor's state and its reads to components: the state of the editor's store, redrawn on each
 * change, and a read that starts again whenever what it asks for changes, keeping only the answer to the latest
 * request.
 */
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { createLatestRequest, settle } from '@hryvinskyi/admin-ui';
import type { EditorState, EditorStore } from './editor-store.ts';

/** The editor's state, redrawn on every change of the store. */
export function useEditorState(store: EditorStore): EditorState {
    const [state, setState] = useState(store.state());
    useEffect(() => {
        setState(store.state());
        return store.subscribe(() => setState(store.state()));
    }, [store]);
    return state;
}

/** Where a read stands: not asked, waiting, answered, or failed. */
export type Remote<T> =
    | { readonly kind: 'idle' }
    | { readonly kind: 'loading' }
    | { readonly kind: 'done'; readonly value: T }
    | { readonly kind: 'failed' };

const IDLE: Remote<never> = Object.freeze({ kind: 'idle' });
const LOADING: Remote<never> = Object.freeze({ kind: 'loading' });
const FAILED: Remote<never> = Object.freeze({ kind: 'failed' });

/**
 * Reads with `load` whenever `key` changes, aborting the read before; null asks nothing. The latest `load` given is
 * the one run, so a caller may build it on every draw. A fault that is not a failed request is reported.
 */
export function useRemote<T>(key: string | null, load: (signal: AbortSignal) => Promise<T>, report: (error: unknown) => void): Remote<T> {
    const [remote, setRemote] = useState<Remote<T>>(key === null ? IDLE : LOADING);
    const latest = useMemo(() => createLatestRequest(report), [report]);
    const loader = useRef(load);
    loader.current = load;
    useEffect(() => {
        if (key === null) {
            latest.cancel();
            setRemote(IDLE);
            return undefined;
        }
        setRemote(LOADING);
        let live = true;
        void latest.run((signal) => loader.current(signal)).then((outcome) => {
            if (live) {
                settle(outcome, { done: (value) => setRemote({ kind: 'done', value }), failed: () => setRemote(FAILED) });
            }
        });
        return () => {
            live = false;
        };
    }, [key, latest]);
    useEffect(() => () => latest.cancel(), [latest]);
    return remote;
}
