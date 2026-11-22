/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The message under the page's header about what just happened: a check the page watched has ended (or stopped), the
 * pages behind a finding were saved, or a request failed — said in the kit's words for the kind of failure, or the
 * page's. It can be dismissed.
 */
import type { JSX } from 'preact';
import { failureText, MessageStrip, useKit, usePage } from '@hryvinskyi/admin-ui';
import type { Toast, ToastKind } from '../application/catalog-state.ts';

export interface TestToastProps {
    readonly toast: Toast | null;
    readonly onDismiss: () => void;
}

interface Strip {
    readonly tone: 'success' | 'error';
    readonly text: string;
}

function stripOf<K extends ToastKind>(strips: { readonly [P in ToastKind]: (toast: Toast<P>) => Strip }, toast: Toast<K>): Strip {
    return strips[toast.kind](toast);
}

export function TestToast({ toast, onDismiss }: TestToastProps): JSX.Element | null {
    const { t } = usePage().translator;
    const kit = useKit().translator;
    if (toast === null) {
        return null;
    }
    const strips: { readonly [P in ToastKind]: (current: Toast<P>) => Strip } = {
        ended: (current) => ({ tone: current.tone, text: current.text }),
        saved: (current) => ({ tone: 'success', text: current.text }),
        failed: (current) => ({ tone: 'error', text: failureText(current.failure, kit, t(current.place)) }),
    };
    const strip = stripOf(strips, toast);
    return (
        <MessageStrip tone={strip.tone} role={strip.tone === 'error' ? 'alert' : 'status'} onDismiss={onDismiss}>
            {strip.text}
        </MessageStrip>
    );
}
