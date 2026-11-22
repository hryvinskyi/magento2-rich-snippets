/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Checks panel: the server's rows about the previewed output — each with its tone's glyph, a title, a text and an
 * optional action — a count on the head (the requirements missing, else the warnings), and the line saying what the
 * checks are compared with.
 *
 * An action that names a property puts it back into the template, with the value the server found for it (see
 * restoredTemplate); one that names none opens the Pages section, where the conditions are.
 */
import type { JSX } from 'preact';
import { Button, Icon, usePage } from '@hryvinskyi/admin-ui';
import type { CheckAction, CheckRow, CheckTone } from '../domain/model.ts';
import { checkCount } from '../domain/preview-view.ts';
import { restoredTemplate } from '../domain/property-restore.ts';
import { useEditorEnv } from './editor-env.tsx';

const TONE_ICONS: { readonly [T in CheckTone]: string } = { ok: 'check_circle', bad: 'error', warn: 'warning', note: 'info' };

const COUNT_KEYS: { readonly [T in 'bad' | 'warn']: string } = { bad: 'editor.checks.errors', warn: 'editor.checks.warnings' };

export interface ChecksPanelProps {
    /** The rows of the latest preview; null before there is one. */
    readonly rows: readonly CheckRow[] | null;
}

export function ChecksPanel({ rows }: ChecksPanelProps): JSX.Element {
    const { translator: { t, tn, n } } = usePage();
    const { store, canChange } = useEditorEnv();
    const count = rows === null ? null : checkCount(rows);

    const run = (action: CheckAction): void => {
        const path = action.path;
        if (path === null) {
            store.request('pages');
            return;
        }
        const restored = restoredTemplate(store.state().draft.template, path, action.value, action.before);
        if (restored !== null) {
            store.setTemplate(restored);
        }
    };

    return (
        <section class="hui-panel rs-editor__checks">
            <div class="hui-panel__head rs-editor__panel-head rs-editor__checks-head">
                <Icon name="fact_check" size={18} class="hui-panel__icon" />
                <h3 class="hui-panel__title">{t('editor.checks.title')}</h3>
                {count === null ? null : (
                    <span class="rs-editor__check-count" data-tone={count.tone} title={tn(COUNT_KEYS[count.tone], count.count)}>
                        {n(count.count)}
                    </span>
                )}
            </div>
            {rows === null ? null : rows.length === 0 ? (
                <p class="rs-editor__check rs-editor__muted">{t('editor.checks.none')}</p>
            ) : rows.map((row, index) => (
                <div key={index} class="rs-editor__check" data-tone={row.tone}>
                    <Icon name={TONE_ICONS[row.tone]} size={20} filled class="rs-editor__check-icon" />
                    <span class="rs-editor__check-words">
                        <span class="rs-editor__check-title">{row.title}</span>
                        <span class="rs-editor__check-text">{row.text}</span>
                        {row.action === null || (row.action.path !== null && !canChange) ? null : (
                            <ActionButton action={row.action} onRun={run} />
                        )}
                    </span>
                </div>
            ))}
            <p class="rs-editor__checks-foot">{t('editor.checks.foot')}</p>
        </section>
    );
}

function ActionButton({ action, onRun }: { readonly action: CheckAction; readonly onRun: (action: CheckAction) => void }): JSX.Element {
    return (
        <span class="rs-editor__check-action">
            <Button variant="link" icon={action.icon} onClick={() => onRun(action)}>{action.label}</Button>
        </span>
    );
}
