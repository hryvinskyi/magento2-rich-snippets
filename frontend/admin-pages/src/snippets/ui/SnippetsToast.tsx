/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The message under the page's header about the last act — a copy made, an export saved, a delete, an import — or
 * what failed or was refused, with a button that takes it away. A failure whose kind has the kit's own words (the
 * session ended, the role forbids it) says those; any other says what could not be done.
 */
import type { JSX } from 'preact';
import { failureText, MessageStrip, useKit, usePage, worded, type Translator } from '@hryvinskyi/admin-ui';
import { refusalOf } from '../domain/refusals.ts';
import type { Act, Toast, ToastKind } from '../application/snippets-state.ts';

export interface SnippetsToastProps {
    readonly toast: Toast | null;
    readonly onDismiss: () => void;
}

const FAILED: { readonly [A in Act]: string } = {
    switch: 'snippets.failed.switch',
    duplicate: 'snippets.failed.duplicate',
    delete: 'snippets.failed.delete',
    export: 'snippets.failed.export',
};

interface Strip {
    readonly tone: 'success' | 'error';
    readonly text: string;
}

function stripOf<K extends ToastKind>(
    strips: { readonly [P in ToastKind]: (toast: Toast<P>) => Strip },
    toast: Toast<K>,
): Strip {
    return strips[toast.kind](toast);
}

export function SnippetsToast({ toast, onDismiss }: SnippetsToastProps): JSX.Element | null {
    const { translator } = usePage();
    const kit = useKit().translator;
    if (toast === null) {
        return null;
    }
    const { t, tn }: Translator = translator;
    const strips: { readonly [P in ToastKind]: (current: Toast<P>) => Strip } = {
        duplicated: (current) => ({ tone: 'success', text: t('snippets.toast.duplicated', current.name) }),
        exported: (current) => ({ tone: 'success', text: t('snippets.toast.exported', current.name, current.fileName) }),
        exportedAll: (current) => ({ tone: 'success', text: tn('snippets.toast.exportedAll', current.count, current.fileName) }),
        deleted: (current) => ({ tone: 'success', text: t('snippets.toast.deleted', current.name) }),
        imported: (current) => ({ tone: 'success', text: tn('snippets.toast.imported', current.count, current.fileName) }),
        failed: (current) => ({ tone: 'error', text: failureText(current.failure, kit, t(FAILED[current.act])) }),
        refused: (current) => ({ tone: 'error', text: worded(translator, refusalOf(current.reason)) }),
    };
    const strip = stripOf(strips, toast);
    return (
        <MessageStrip tone={strip.tone} role={strip.tone === 'error' ? 'alert' : 'status'} onDismiss={onDismiss}>
            {strip.text}
        </MessageStrip>
    );
}
