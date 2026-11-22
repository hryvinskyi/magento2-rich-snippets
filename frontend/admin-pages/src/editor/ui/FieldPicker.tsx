/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The field picker: a search over the catalog's fields and attributes, listed by group, each with what it holds on
 * the sample page; the field picked is marked.
 */
import type { JSX } from 'preact';
import { useState } from 'preact/hooks';
import { CardState, TextInput, usePage } from '@hryvinskyi/admin-ui';
import { pickableGroups } from '../domain/drawer-view.ts';
import type { FieldCatalog } from '../domain/model.ts';

export interface FieldPickerProps {
    readonly id: string;
    readonly catalog: FieldCatalog | null;
    readonly state: 'loading' | 'failed' | 'done' | 'idle';
    readonly kinds: readonly string[];
    /** The list items the property is inside, whose fields it may read. */
    readonly itemNames: ReadonlySet<string>;
    readonly selected: string;
    readonly onPick: (path: string) => void;
    readonly disabled: boolean;
}

export function FieldPicker({ id, catalog, state, kinds, itemNames, selected, onPick, disabled }: FieldPickerProps): JSX.Element {
    const { translator: { t } } = usePage();
    const [query, setQuery] = useState('');
    if (catalog === null) {
        return state === 'failed'
            ? <CardState kind="failed" title={t('editor.field.failed')} />
            : <p class="rs-editor__muted" role="status">{t('editor.field.loading')}</p>;
    }
    const groups = pickableGroups(catalog, query, kinds, itemNames);
    return (
        <div class="rs-editor__picker">
            <TextInput id={id} type="search" value={query} onInput={setQuery} label={t('editor.field.search')} placeholder={t('editor.field.search')} width="full" />
            {groups.length === 0 ? <p class="rs-editor__muted">{t('editor.field.none')}</p> : null}
            <div class="rs-editor__picker-list" role="listbox" aria-label={t('editor.field.label')}>
                {groups.map((group) => (
                    <div key={group.code} class="rs-editor__picker-group" role="group" aria-label={group.label}>
                        <div class="rs-editor__picker-heading">{group.label}</div>
                        {group.fields.map((field) => (
                            <button
                                key={field.path}
                                type="button"
                                role="option"
                                aria-selected={field.path === selected}
                                class="rs-editor__picker-field"
                                disabled={disabled}
                                onClick={() => onPick(field.path)}
                            >
                                <span class="rs-editor__picker-label">{field.label}</span>
                                <code class="rs-editor__picker-path">{field.attributeCode ?? field.path}</code>
                                <span class="rs-editor__picker-example">{field.example ?? t('editor.field.empty')}</span>
                            </button>
                        ))}
                    </div>
                ))}
            </div>
            <p class="rs-editor__muted">
                {catalog.sample === null ? t('editor.field.helpNoSample') : t('editor.field.help', catalog.sample.label)}
            </p>
        </div>
    );
}
