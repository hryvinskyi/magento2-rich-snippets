/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Preview panel: the sample picker (when the snippet has more than one sample page), the page previewed — its kind
 * and store view, or the snippet a building block is shown inside — with its path, then the output as coloured JSON
 * with a note and a Copy button, or the box saying why nothing is output. While the Code tab holds text that is not a
 * template, the output is the last valid version's and the note says so.
 */
import type { JSX } from 'preact';
import { useEffect, useRef, useState } from 'preact/hooks';
import { Button, codeLines, Icon, Notice, SelectInput, usePage } from '@hryvinskyi/admin-ui';
import { useEditorState } from '../application/use-editor.ts';
import type { PreviewControls } from '../application/use-preview.ts';
import type { PreviewResult, Sample } from '../domain/model.ts';
import { outputNote, outputText, type OutputNote } from '../domain/preview-view.ts';
import { templateTokens } from '../domain/template-lexer.ts';
import { useEditorEnv } from './editor-env.tsx';
import { useEditorView } from './editor-view.tsx';

/** How long the Copy button says "Copied". */
export const COPIED_MS = 1600;

const NOTE_KEYS: { readonly [N in OutputNote]: string } = {
    lastValid: 'editor.preview.noteLastValid',
    block: 'editor.preview.noteBlock',
    page: 'editor.preview.notePage',
};

function NothingBox({ title, text }: { readonly title: string; readonly text: string }): JSX.Element {
    return (
        <div class="rs-editor__nothing">
            <span class="rs-editor__nothing-title">{title}</span>
            <span class="rs-editor__nothing-text">{text}</span>
        </div>
    );
}

function OutputCode({ text, label }: { readonly text: string; readonly label: string }): JSX.Element {
    return (
        <div class="rs-editor__output">
            <div class="rs-editor__output-scroll" tabIndex={0} role="group" aria-label={label}>
                {codeLines(text, templateTokens(text)).map((line) => (
                    <div key={line.key} class="rs-editor__output-line">
                        {line.segments.map((segment, index) => <span key={index} data-kind={segment.kind}>{segment.text}</span>)}
                    </div>
                ))}
            </div>
        </div>
    );
}

/** The Copy button: copies the output and says "Copied" for a moment. */
function CopyButton({ text }: { readonly text: string }): JSX.Element {
    const { translator: { t } } = usePage();
    const { ports, report } = useEditorEnv();
    const [copied, setCopied] = useState(false);
    const cancel = useRef<(() => void) | null>(null);
    useEffect(() => () => cancel.current?.(), []);
    const copy = (): void => {
        ports.clipboard.write(text).then(() => {
            cancel.current?.();
            setCopied(true);
            cancel.current = ports.scheduler.after(COPIED_MS, () => setCopied(false));
        }, report);
    };
    return (
        <Button variant="link" icon={copied ? 'check' : 'content_copy'} onClick={copy}>
            {t(copied ? 'editor.preview.copied' : 'editor.preview.copy')}
        </Button>
    );
}

export interface PreviewPanelProps {
    readonly controls: PreviewControls;
    readonly samples: readonly Sample[];
    readonly loadingSamples: boolean;
}

export function PreviewPanel({ controls, samples, loadingSamples }: PreviewPanelProps): JSX.Element {
    const { translator: { t } } = usePage();
    const { store, kindsByCode, stores, usedIn } = useEditorEnv();
    const { store: readIn } = useEditorView();
    const { draft, preview, codeInvalid } = useEditorState(store);
    const { standing } = controls;
    const shown: PreviewResult | null = controls.sample === null ? null : preview;
    const sample = shown?.sample ?? controls.sample;
    const page = sample === null ? '' : (kindsByCode.get(sample.kind)?.page ?? sample.kind);
    const storeName = stores.find((view) => view.id === readIn)?.name ?? null;
    const parent = usedIn[0]?.name ?? null;
    const meta = draft.isBlock
        ? (parent === null ? page : t('editor.preview.metaBlock', parent, page))
        : (storeName === null ? page : t('editor.preview.meta', page, storeName));
    const text = outputText(shown);

    return (
        <section class="hui-panel rs-editor__preview" aria-busy={standing === 'loading' || standing === 'waiting'}>
            <div class="hui-panel__head rs-editor__panel-head">
                <Icon name="visibility" size={18} class="hui-panel__icon" />
                <h3 class="hui-panel__title">{t('editor.preview.title')}</h3>
                {samples.length > 1 && controls.sample !== null ? (
                    <span class="rs-editor__sample">
                        <SelectInput
                            value={controls.sample.ref}
                            options={samples.map((option) => ({ value: option.ref, label: option.label }))}
                            onChange={controls.pick}
                            compact
                            label={t('editor.preview.sample')}
                        />
                    </span>
                ) : null}
            </div>
            <div class="hui-panel__body rs-editor__preview-body">
                {sample === null ? null : (
                    <p class="rs-editor__preview-meta">
                        <span>{meta}</span>
                        <span class="rs-editor__preview-path">{sample.path === '' ? sample.label : sample.path}</span>
                    </p>
                )}
                {standing === 'failed' ? (
                    <Notice tone="error" action={{ label: t('editor.preview.retry'), onClick: controls.retry }}>{t('editor.preview.failed')}</Notice>
                ) : null}
                {sample === null && !loadingSamples ? <NothingBox title={t('editor.preview.noPage.title')} text={t('editor.preview.noPage.text')} /> : null}
                {shown === null && sample !== null && standing !== 'failed' ? (
                    <p class="rs-editor__muted" role="status">{t('editor.preview.loading')}</p>
                ) : null}
                {shown !== null && shown.output !== null ? (
                    <>
                        <OutputCode text={text} label={t('editor.preview.output')} />
                        <div class="rs-editor__preview-foot">
                            <span class="rs-editor__preview-note">{t(NOTE_KEYS[outputNote(draft.isBlock, codeInvalid)])}</span>
                            <CopyButton text={text} />
                        </div>
                    </>
                ) : null}
                {shown !== null && shown.message !== null ? <NothingBox title={shown.message.title} text={shown.message.text} /> : null}
            </div>
        </section>
    );
}
