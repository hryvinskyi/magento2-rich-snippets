/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The hook binding the save to a component: a save session that performs every Save the editor's store announces,
 * redraws on each change of its state, tells `created` of a snippet it created, and stops when the component goes.
 */
import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import type { Draft } from '../domain/model.ts';
import type { EditorStore } from './editor-store.ts';
import type { DraftGateway, IdempotencyKeys } from './ports.ts';
import { createSaveSession, type SaveState } from './save-session.ts';

export interface SaveControls {
    readonly state: SaveState;
    save(): void;
    dismiss(): void;
}

export function useSave(
    store: EditorStore,
    ports: { readonly drafts: DraftGateway; readonly keys: IdempotencyKeys },
    created: (draft: Draft) => void,
    report: (error: unknown) => void,
): SaveControls {
    const callbacks = useRef({ created, report });
    callbacks.current = { created, report };
    const session = useMemo(() => createSaveSession({
        store,
        gateway: ports.drafts,
        keys: ports.keys,
        created: (draft) => callbacks.current.created(draft),
        report: (error) => callbacks.current.report(error),
    }), [store, ports]);
    const [state, setState] = useState<SaveState>(session.state());
    useEffect(() => {
        setState(session.state());
        const stopListening = session.subscribe(() => setState(session.state()));
        const stopHearing = store.on('save', () => void session.save());
        return () => {
            stopListening();
            stopHearing();
            session.stop();
        };
    }, [session, store]);
    return {
        state,
        save: (): void => void session.save(),
        dismiss: (): void => session.dismiss(),
    };
}
