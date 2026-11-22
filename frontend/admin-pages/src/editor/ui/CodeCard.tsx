/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Properties card in Code mode: the snippet's template in the kit's code editor, coloured, with the store's fields
 * offered after two braces, and the status bar saying whether the text is a template — "Valid." with its lines and
 * fields, or the first problem in words with its line marked.
 *
 * Text that is a template goes into the draft at once (the builder, the preview and Save follow it); text that is not
 * stays here while the draft keeps the last valid template, and the editor's store is told so Save stays off. A
 * template changed elsewhere (a check that puts a property back, a new type) shows here while the text is valid.
 */
import type { ComponentChildren, JSX } from 'preact';
import { useEffect, useMemo, useState } from 'preact/hooks';
import { CardFrame, CodeEditor, usePage, type EditorStatus, type Translator } from '@hryvinskyi/admin-ui';
import { useEditorState } from '../application/use-editor.ts';
import { offerFields } from '../domain/field-completion.ts';
import { checkTemplate, type ProblemCode, type TemplateCheck, type TemplateProblem } from '../domain/template-check.ts';
import { templateTokens } from '../domain/template-lexer.ts';
import { useEditorEnv } from './editor-env.tsx';
import { useEditorView } from './editor-view.tsx';

export const CODE_EDITOR_ID = 'rs-editor-code';

type Worded<K extends ProblemCode> = (translator: Translator, problem: Extract<TemplateProblem, { readonly code: K }>) => EditorStatus;

/** Each problem in words; a line half-written reads as work in progress, anything else as a mistake. */
const PROBLEM_WORDS: { readonly [K in ProblemCode]: Worded<K> } = {
    unfinished: ({ t }, problem) => ({ tone: 'pending', message: t('editor.code.unfinished', problem.line) }),
    'missing-comma': ({ t }, problem) => ({ tone: 'error', message: t('editor.code.missingComma', problem.line, problem.after) }),
    'trailing-comma': ({ t }, problem) => ({ tone: 'error', message: t('editor.code.trailingComma', problem.line) }),
    unclosed: ({ t }, problem) => ({ tone: 'error', message: t('editor.code.unclosed', problem.line, problem.bracket) }),
    unexpected: ({ t }, problem) => ({ tone: 'error', message: t('editor.code.unexpected', problem.line) }),
};

function problemStatus<K extends ProblemCode>(translator: Translator, problem: Extract<TemplateProblem, { readonly code: K }>): EditorStatus {
    const words: Worded<K> = PROBLEM_WORDS[problem.code];
    return words(translator, problem);
}

function statusOf(translator: Translator, check: TemplateCheck): EditorStatus {
    if (check.kind === 'invalid') {
        return problemStatus(translator, check.problem);
    }
    const { t, tn } = translator;
    return { tone: 'ok', message: t('editor.code.valid', tn('editor.code.lines', check.lines), tn('editor.code.fields', check.fields)) };
}

export interface CodeCardProps {
    /** The choice of views at the right end of the card's head. */
    readonly aside?: ComponentChildren | undefined;
}

export function CodeCard({ aside }: CodeCardProps): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const { store, canChange } = useEditorEnv();
    const { catalog } = useEditorView();
    const { draft } = useEditorState(store);
    const [text, setText] = useState(draft.template);
    const check = useMemo(() => checkTemplate(text), [text]);

    useEffect(() => {
        store.showCodeInvalid(check.kind === 'invalid');
    }, [store, check]);
    useEffect(() => () => store.showCodeInvalid(false), [store]);
    useEffect(() => {
        if (draft.template !== text && check.kind === 'valid') {
            setText(draft.template);
        }
    }, [draft.template]);

    const change = (next: string): void => {
        setText(next);
        if (checkTemplate(next).kind === 'valid') {
            store.setTemplate(next);
        }
    };

    return (
        <CardFrame icon="account_tree" title={t('editor.properties.title')} subtitle={t('editor.properties.subtitleCode')} aside={aside}>
            <div class="rs-editor__code">
                <CodeEditor
                    id={CODE_EDITOR_ID}
                    value={text}
                    onChange={canChange ? change : undefined}
                    label={t('editor.code.area')}
                    tokenize={templateTokens}
                    complete={(current, caret) => offerFields(catalog, current, caret)}
                    completionLabel={t('editor.code.listLabel')}
                    completionFooter={t('editor.code.listFooter')}
                    markedLine={check.kind === 'invalid' ? check.problem.line : null}
                    status={statusOf(translator, check)}
                    hint={t('editor.code.hint')}
                    rows={12}
                    maxRows={40}
                />
            </div>
        </CardFrame>
    );
}
