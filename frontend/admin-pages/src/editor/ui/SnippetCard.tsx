/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippet card: the snippet's name and schema.org type. A building block also says that it has no page of its
 * own and lists the snippets that use it, each linked to its editor.
 */
import type { JSX } from 'preact';
import { CardFrame, describedBy, FieldRow, linkTo, Notice, TextInput, usePage, withParam } from '@hryvinskyi/admin-ui';
import { withType } from '../domain/draft.ts';
import type { Draft } from '../domain/model.ts';
import { useEditorEnv } from './editor-env.tsx';

const NAME_ID = 'rs-editor-name';
const TYPE_ID = 'rs-editor-type';
const EDIT_ROUTE = 'hryvinskyi_richsnippets/snippets/edit';

export interface SnippetCardProps {
    readonly draft: Draft;
    readonly typeError: boolean;
}

export function SnippetCard({ draft, typeError }: SnippetCardProps): JSX.Element {
    const { translator: { t }, link } = usePage();
    const { store, usedIn, canChange } = useEditorEnv();
    return (
        <CardFrame
            icon="data_object"
            title={t('editor.snippet.title')}
            subtitle={t(draft.isBlock ? 'editor.snippet.subtitleBlock' : 'editor.snippet.subtitle')}
        >
            <div class="hui-card__body">
                <div class="rs-editor__fields">
                    <FieldRow controlId={NAME_ID} label={t('editor.snippet.name')} help={t('editor.snippet.nameHelp')}>
                        <TextInput
                            id={NAME_ID}
                            value={draft.name}
                            placeholder={t('editor.snippet.namePlaceholder')}
                            disabled={!canChange}
                            describedBy={describedBy(NAME_ID, { help: true, error: false })}
                            onInput={(name) => store.change((current) => ({ ...current, name }))}
                        />
                    </FieldRow>
                    <FieldRow
                        controlId={TYPE_ID}
                        label={t('editor.snippet.type')}
                        help={t('editor.snippet.typeHelp')}
                        problems={typeError ? [t('editor.snippet.typeError')] : []}
                    >
                        <TextInput
                            id={TYPE_ID}
                            value={draft.type}
                            mono
                            invalid={typeError}
                            placeholder={t('editor.snippet.typePlaceholder')}
                            disabled={!canChange}
                            describedBy={describedBy(TYPE_ID, { help: true, error: typeError })}
                            onInput={(type) => store.change((current) => withType(current, type))}
                        />
                    </FieldRow>
                </div>
                {draft.isBlock ? (
                    <div class="rs-editor__block">
                        <Notice tone="info" icon="widgets">{t('editor.snippet.block')}</Notice>
                        <h3 class="rs-editor__used-title">{t('editor.snippet.usedIn')}</h3>
                        {usedIn.length === 0 ? (
                            <p class="rs-editor__muted">{t('editor.snippet.usedInNone')}</p>
                        ) : (
                            <ul class="rs-editor__used">
                                {usedIn.map((includer) => {
                                    const href = includer.id === null ? null : link(withParam(linkTo(EDIT_ROUTE), 'id', String(includer.id)));
                                    return (
                                        <li key={includer.code} class="rs-editor__used-item">
                                            {href === null ? <span>{includer.name}</span> : <a href={href}>{includer.name}</a>}
                                            <code class="rs-editor__used-paths">{includer.paths.join(', ')}</code>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>
                ) : null}
            </div>
        </CardFrame>
    );
}
