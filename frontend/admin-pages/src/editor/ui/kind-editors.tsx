/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The drawer's editor for each kind of value, one per kind, chosen by the value's kind. Each shows the value and
 * hands back the changed value; the drawer decides when it is complete enough to reach the template.
 */
import type { JSX } from 'preact';
import { useState } from 'preact/hooks';
import { Button, CardState, FieldRow, RadioCards, SelectInput, TextArea, TextInput, usePage } from '@hryvinskyi/admin-ui';
import { pickableGroups } from '../domain/drawer-view.ts';
import { JsonSyntaxError, jsonText, printJson, readJson } from '../domain/json-document.ts';
import type { FieldCatalog, IncludeCandidate, ValueDefaults } from '../domain/model.ts';
import type { ListSource, Value, ValueKind } from '../domain/template-tree.ts';
import { FieldPicker } from './FieldPicker.tsx';

export interface KindEditorContext {
    /** The property's name; empty for an item. */
    readonly name: string;
    readonly catalog: FieldCatalog | null;
    readonly catalogState: 'loading' | 'failed' | 'done' | 'idle';
    readonly candidates: readonly IncludeCandidate[] | null;
    readonly candidatesState: 'loading' | 'failed' | 'done' | 'idle';
    readonly kinds: readonly string[];
    readonly itemNames: ReadonlySet<string>;
    readonly defaults: ValueDefaults;
    readonly disabled: boolean;
}

export interface KindEditorProps<K extends ValueKind> {
    readonly value: Value<K>;
    readonly onChange: (value: Value) => void;
    readonly context: KindEditorContext;
}

const ID = 'rs-editor-value';

function FieldEditor({ value, onChange, context }: KindEditorProps<'field'>): JSX.Element {
    const { translator: { t } } = usePage();
    const instead = value.fallback !== null;
    return (
        <div class="rs-editor__kind-editor">
            <FieldRow controlId={`${ID}-field`} label={t('editor.field.label')}>
                <FieldPicker
                    id={`${ID}-field`}
                    catalog={context.catalog}
                    state={context.catalogState}
                    kinds={context.kinds}
                    itemNames={context.itemNames}
                    selected={value.path}
                    disabled={context.disabled}
                    onPick={(path) => onChange({ ...value, path })}
                />
            </FieldRow>
            <RadioCards
                name={`${ID}-empty`}
                label={t('editor.field.whenEmpty')}
                variant="plain"
                value={instead ? 'instead' : 'leave'}
                disabled={context.disabled}
                options={[
                    { value: 'leave', label: t('editor.field.leaveOut') },
                    { value: 'instead', label: t('editor.field.useInstead') },
                ]}
                onChange={(choice) => onChange({ ...value, fallback: choice === 'instead' ? (value.fallback ?? '') : null })}
            />
            {instead ? (
                <TextInput
                    id={`${ID}-instead`}
                    value={value.fallback ?? ''}
                    label={t('editor.field.insteadLabel')}
                    placeholder={t('editor.field.insteadPlaceholder')}
                    disabled={context.disabled}
                    onInput={(fallback) => onChange({ ...value, fallback })}
                />
            ) : null}
        </div>
    );
}

const literalText = (value: Value<'fixed'>): string => {
    const literal = value.literal;
    return literal.type === 'text' ? literal.value : literal.type === 'number' ? literal.raw : String(literal.value);
};

function FixedEditor({ value, onChange, context }: KindEditorProps<'fixed'>): JSX.Element {
    const { translator: { t } } = usePage();
    const enums = context.catalog?.enums.get(context.name) ?? [];
    return (
        <div class="rs-editor__kind-editor">
            <FieldRow controlId={`${ID}-fixed`} label={t('editor.fixed.label')}>
                <TextInput
                    id={`${ID}-fixed`}
                    value={literalText(value)}
                    width="full"
                    disabled={context.disabled}
                    onInput={(text) => onChange({ ...value, literal: text === literalText(value) ? value.literal : jsonText(text) })}
                />
            </FieldRow>
            {enums.length === 0 ? null : (
                <div class="rs-editor__enums">
                    <p class="rs-editor__muted">{t('editor.fixed.enums', context.name)}</p>
                    {enums.map((option) => (
                        <Button key={option} variant="secondary" disabled={context.disabled} onClick={() => onChange({ ...value, literal: jsonText(`https://schema.org/${option}`) })}>
                            {option}
                        </Button>
                    ))}
                </div>
            )}
        </div>
    );
}

function TextEditor({ value, onChange, context }: KindEditorProps<'text'>): JSX.Element {
    const { translator: { t } } = usePage();
    const fields = context.catalog === null
        ? []
        : pickableGroups(context.catalog, '', context.kinds, context.itemNames).flatMap((group) =>
            group.fields.map((field) => ({ value: field.path, label: `${group.label} › ${field.label}` })));
    return (
        <div class="rs-editor__kind-editor">
            <FieldRow controlId={`${ID}-text`} label={t('editor.text.label')} help={t('editor.text.help')}>
                <TextArea id={`${ID}-text`} value={value.text} rows={3} disabled={context.disabled} onInput={(text) => onChange({ ...value, text })} />
            </FieldRow>
            {fields.length === 0 ? null : (
                <SelectInput
                    value=""
                    label={t('editor.text.insert')}
                    disabled={context.disabled}
                    options={[{ value: '', label: t('editor.text.insert') }, ...fields]}
                    onChange={(path) => {
                        if (path !== '') {
                            onChange({ ...value, text: `${value.text}{{${path}}}` });
                        }
                    }}
                />
            )}
        </div>
    );
}

function ObjectEditor({ value, onChange, context }: KindEditorProps<'object'>): JSX.Element {
    const { translator: { t } } = usePage();
    return (
        <div class="rs-editor__kind-editor">
            <FieldRow controlId={`${ID}-object`} label={t('editor.object.type')} help={t('editor.object.help')}>
                <TextInput
                    id={`${ID}-object`}
                    value={value.types.join(', ')}
                    mono
                    placeholder={t('editor.object.placeholder')}
                    disabled={context.disabled}
                    onInput={(types) => onChange({ ...value, types: types.split(',').map((type) => type.trim()).filter((type) => type !== '') })}
                />
            </FieldRow>
        </div>
    );
}

function SnippetEditor({ value, onChange, context }: KindEditorProps<'snippet'>): JSX.Element {
    const { translator: { t } } = usePage();
    if (context.candidates === null) {
        return context.candidatesState === 'failed'
            ? <CardState kind="failed" title={t('editor.include.failed')} />
            : <p class="rs-editor__muted" role="status">{t('editor.include.loading')}</p>;
    }
    if (context.candidates.length === 0) {
        return <p class="rs-editor__muted">{t('editor.include.none')}</p>;
    }
    return (
        <div class="rs-editor__kind-editor">
            <RadioCards
                name={`${ID}-snippet`}
                label={t('editor.include.label')}
                value={value.code}
                disabled={context.disabled}
                options={context.candidates.map((candidate) => ({
                    value: candidate.code,
                    label: candidate.name === '' ? candidate.code : candidate.name,
                    description: candidate.loops ? t('editor.include.loops') : candidate.description,
                    icon: candidate.isBlock ? 'widgets' : 'data_object',
                    disabled: candidate.loops,
                }))}
                onChange={(code) => onChange({ ...value, code })}
            />
            <p class="rs-editor__muted">{t('editor.include.help')}</p>
        </div>
    );
}

const storeSource = (defaults: ValueDefaults): ListSource => ({
    kind: 'store',
    each: defaults.list.each,
    as: null,
    limit: null,
    item: { kind: 'field', path: defaults.list.item, filters: [], fallback: null },
});

function ListEditor({ value, onChange, context }: KindEditorProps<'list'>): JSX.Element {
    const { translator: { t } } = usePage();
    const source = value.source;
    const lists = context.catalog?.lists ?? [];
    const options = lists.map((list) => ({ value: list.path, label: list.label }));
    if (source.kind === 'store' && !lists.some((list) => list.path === source.each)) {
        options.unshift({ value: source.each, label: source.each });
    }
    return (
        <div class="rs-editor__kind-editor">
            <RadioCards
                name={`${ID}-list`}
                label={t('editor.kind.list')}
                variant="plain"
                direction="row"
                value={source.kind}
                disabled={context.disabled}
                options={[{ value: 'store', label: t('editor.list.of') }, { value: 'items', label: t('editor.list.entered') }]}
                onChange={(kind) => {
                    if (kind !== source.kind) {
                        onChange({ ...value, source: kind === 'items' ? { kind: 'items', items: [] } : storeSource(context.defaults) });
                    }
                }}
            />
            {source.kind === 'store' ? (
                <>
                    <FieldRow controlId={`${ID}-each`} label={t('editor.list.of')} help={t('editor.list.helpStore')}>
                        <SelectInput
                            id={`${ID}-each`}
                            value={source.each}
                            options={options}
                            disabled={context.disabled}
                            onChange={(each) => onChange({ ...value, source: { ...source, each } })}
                        />
                    </FieldRow>
                    <FieldRow controlId={`${ID}-limit`} label={t('editor.list.max')} help={t('editor.list.maxNote')}>
                        <TextInput
                            id={`${ID}-limit`}
                            value={source.limit === null ? '' : String(source.limit)}
                            width="short"
                            suffix={t('editor.list.items')}
                            disabled={context.disabled}
                            onInput={(text) => {
                                const limit = /^\d+$/.test(text.trim()) ? Number(text.trim()) : null;
                                onChange({ ...value, source: { ...source, limit: limit === 0 ? null : limit } });
                            }}
                        />
                    </FieldRow>
                </>
            ) : <p class="rs-editor__muted">{t('editor.list.helpItems')}</p>}
        </div>
    );
}

function CodeEditor({ value, onChange, context }: KindEditorProps<'code'>): JSX.Element {
    const { translator: { t } } = usePage();
    const [text, setText] = useState(() => printJson(value.json, 0));
    const [invalid, setInvalid] = useState(false);
    const input = (next: string): void => {
        setText(next);
        try {
            onChange({ ...value, json: readJson(next) });
            setInvalid(false);
        } catch (error) {
            if (!(error instanceof JsonSyntaxError)) {
                throw error;
            }
            setInvalid(true);
        }
    };
    return (
        <div class="rs-editor__kind-editor">
            <FieldRow controlId={`${ID}-code`} label={t('editor.code.label')} help={t('editor.code.help')}>
                <TextArea id={`${ID}-code`} value={text} rows={6} invalid={invalid} disabled={context.disabled} onInput={input} />
            </FieldRow>
        </div>
    );
}

const EDITORS: { readonly [K in ValueKind]: (props: KindEditorProps<K>) => JSX.Element } = {
    field: (props) => <FieldEditor {...props} />,
    fixed: (props) => <FixedEditor {...props} />,
    text: (props) => <TextEditor {...props} />,
    object: (props) => <ObjectEditor {...props} />,
    snippet: (props) => <SnippetEditor {...props} />,
    list: (props) => <ListEditor {...props} />,
    code: (props) => <CodeEditor {...props} />,
};

/** The editor of the value's kind. */
export function KindEditor<K extends ValueKind>(props: KindEditorProps<K>): JSX.Element {
    return EDITORS[props.value.kind](props);
}

/** The glyph of each kind of value. */
export const KIND_ICONS: { readonly [K in ValueKind]: string } = {
    field: 'database',
    fixed: 'label',
    text: 'text_fields',
    object: 'data_object',
    snippet: 'widgets',
    list: 'format_list_numbered',
    code: 'code',
};
