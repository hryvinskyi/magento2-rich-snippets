/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The message under the editor's header after a save: saved (what the new version reaches, dismissible), refused
 * (the server's reason, dismissible), someone else's save first (the server's sentence, with Reload), or no answer
 * (with Try again, which saves again). Nothing while nothing was saved, or while a save is on its way.
 */
import type { JSX } from 'preact';
import { MessageStrip, usePage, type Translator } from '@hryvinskyi/admin-ui';
import type { SaveState } from '../application/save-session.ts';
import type { SavedReach } from '../domain/save-view.ts';

const REACH_KEYS: { readonly [R in SavedReach]: string } = {
    block: 'editor.save.block',
    live: 'editor.save.live',
    off: 'editor.save.off',
};

export interface SaveNoticeActions {
    readonly retry: () => void;
    readonly reload: () => void;
    readonly dismiss: () => void;
}

type Strip<K extends SaveState['kind']> = (
    state: Extract<SaveState, { readonly kind: K }>,
    translator: Translator,
    actions: SaveNoticeActions,
) => JSX.Element | null;

const NOTHING = (): null => null;

const STRIPS: { readonly [K in SaveState['kind']]: Strip<K> } = {
    idle: NOTHING,
    saving: NOTHING,
    saved: (state, { t }, { dismiss }) => (
        <MessageStrip tone="success" onDismiss={dismiss}>
            {`${t('editor.save.saved', state.name === '' ? t('editor.save.snippet') : state.name)} ${t(REACH_KEYS[state.reach])}`}
        </MessageStrip>
    ),
    refused: (state, { t }, { dismiss }) => (
        <MessageStrip tone="error" onDismiss={dismiss}>{t('editor.save.refused', state.message)}</MessageStrip>
    ),
    conflict: (state, { t }, { reload }) => (
        <MessageStrip tone="error" action={{ label: t('editor.save.reload'), onClick: reload }}>{state.message}</MessageStrip>
    ),
    failed: (_state, { t }, { retry }) => (
        <MessageStrip tone="error" action={{ label: t('editor.save.retry'), onClick: retry }}>{t('editor.save.failed')}</MessageStrip>
    ),
};

function stripOf<K extends SaveState['kind']>(state: Extract<SaveState, { readonly kind: K }>, translator: Translator, actions: SaveNoticeActions): JSX.Element | null {
    const strip: Strip<K> = STRIPS[state.kind];
    return strip(state, translator, actions);
}

export function SaveNotice({ state, actions }: { readonly state: SaveState; readonly actions: SaveNoticeActions }): JSX.Element | null {
    const { translator } = usePage();
    return stripOf(state, translator, actions);
}
