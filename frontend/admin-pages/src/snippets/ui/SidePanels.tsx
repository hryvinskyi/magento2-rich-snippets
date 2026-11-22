/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The side column: how snippets are output — where a snippet that is on writes its block (the server's line, after
 * the store views' Position setting), that a building block is written only inside the snippets that use it, and when
 * a change shows — and, for an admin who may change snippets, moving snippets between shops with *Export all* (while a
 * list with snippets is shown) and *Import* (while a list is shown).
 */
import type { JSX } from 'preact';
import { Button, Panel, usePage } from '@hryvinskyi/admin-ui';

export function OutputPanel({ outputLine }: { readonly outputLine: string }): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('snippets.output.title')} icon="info">
            <div class="rs-snippets__aside-text">
                <p>{outputLine}</p>
                <p>{t('snippets.output.block')}</p>
                <p>{t('snippets.output.refresh')}</p>
            </div>
        </Panel>
    );
}

export interface MovePanelProps {
    readonly canExport: boolean;
    readonly canImport: boolean;
    readonly onExport: () => void;
    readonly onImport: () => void;
}

export function MovePanel({ canExport, canImport, onExport, onImport }: MovePanelProps): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('snippets.move.title')} icon="swap_horiz">
            <p class="rs-snippets__aside-text">{t('snippets.move.text')}</p>
            <div class="hui-actions">
                <Button variant="tertiary" icon="download" disabled={!canExport} onClick={onExport}>{t('snippets.move.export')}</Button>
                <Button variant="tertiary" icon="upload" disabled={!canImport} onClick={onImport}>{t('snippets.move.import')}</Button>
            </div>
        </Panel>
    );
}
