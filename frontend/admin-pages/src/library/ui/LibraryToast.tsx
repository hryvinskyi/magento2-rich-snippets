/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The message under the page's header about the last add: the copy's name with a link to its editor, or how many
 * snippets the recommended set added with a link to the page test; each link drawn only for an admin who may open its
 * screen, and a close button in its place otherwise. A recommended set that could not be added says so in the kit's
 * words for the kind of failure, or the page's.
 */
import type { JSX } from 'preact';
import { failureText, MessageStrip, useKit, usePage, type Translator } from '@hryvinskyi/admin-ui';
import { addedMessageOf, setMessageOf, type AddMessage, type Sentence } from '../domain/add-view.ts';
import type { Toast, ToastKind } from '../application/library-state.ts';

export interface LibraryToastProps {
    readonly toast: Toast | null;
    readonly onDismiss: () => void;
}

/** A sentence in the page's words, in the plural form its count asks for. */
export const sentenceText = (translator: Translator, sentence: Sentence): string => (sentence.count === null
    ? translator.t(sentence.phrase.key, ...sentence.phrase.params)
    : translator.tn(sentence.phrase.key, sentence.count, ...sentence.phrase.params));

interface Strip {
    readonly tone: 'success' | 'error';
    readonly text: string;
    readonly link: { readonly label: string; readonly href: string } | null;
}

function stripOf<K extends ToastKind>(strips: { readonly [P in ToastKind]: (toast: Toast<P>) => Strip }, toast: Toast<K>): Strip {
    return strips[toast.kind](toast);
}

export function LibraryToast({ toast, onDismiss }: LibraryToastProps): JSX.Element | null {
    const { translator, link } = usePage();
    const kit = useKit().translator;
    if (toast === null) {
        return null;
    }
    const success = (message: AddMessage): Strip => {
        const href = message.link === null ? null : link(message.link.to);
        return {
            tone: 'success',
            text: sentenceText(translator, message.sentence),
            link: message.link === null || href === null
                ? null
                : { label: translator.t(message.link.label.key, ...message.link.label.params), href },
        };
    };
    const strips: { readonly [P in ToastKind]: (current: Toast<P>) => Strip } = {
        added: (current) => success(addedMessageOf(current.answer, current.template)),
        set: (current) => success(setMessageOf(current.answer)),
        failed: (current) => ({
            tone: 'error',
            text: failureText(current.failure, kit, translator.t('library.failed.addRecommended')),
            link: null,
        }),
    };
    const strip = stripOf(strips, toast);
    return (
        <MessageStrip
            tone={strip.tone}
            role={strip.tone === 'error' ? 'alert' : 'status'}
            link={strip.link ?? undefined}
            onDismiss={strip.link === null ? onDismiss : undefined}
        >
            {strip.text}
        </MessageStrip>
    );
}
