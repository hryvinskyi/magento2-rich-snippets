/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Properties card in Builder mode: the root bar, a row per property in the order the template writes them, each
 * with the chips that say where its value comes from, the field it is output only when, and — once a preview exists
 * — whether it is empty or skipped on the preview's page. A row with rows under it collapses; a plus adds a property
 * or an item under it. A click on a row opens the property drawer.
 *
 * A template that is not JSON, or whose top level the builder cannot draw, is shown as such and left to the Code tab;
 * a blank snippet suggests types to start from.
 */
import type { ComponentChildren, JSX } from 'preact';
import { useMemo, useState } from 'preact/hooks';
import { Badge, Button, CardFrame, CardState, Icon, linkTo, LinkButton, Notice, usePage } from '@hryvinskyi/admin-ui';
import { withType } from '../domain/draft.ts';
import type { Draft, FieldCatalog, IncludeCandidate, PreviewResult } from '../domain/model.ts';
import { initiallyCollapsed, pathKey, rootType, rowMarker, rowsOf, type Chip, type Row, type RowMarker } from '../domain/row-view.ts';
import { parseTemplate } from '../domain/template-projection.ts';
import type { Tree } from '../domain/template-tree.ts';
import type { SlotPath } from '../domain/tree-edits.ts';
import { useEditorEnv } from './editor-env.tsx';
import { PropertyDrawer, type DrawerTarget } from './PropertyDrawer.tsx';

const LIBRARY_ROUTE = 'hryvinskyi_richsnippets/library/index';

export interface PropertiesCardProps {
    readonly draft: Draft;
    readonly catalog: FieldCatalog | null;
    readonly catalogState: 'loading' | 'failed' | 'done' | 'idle';
    readonly candidates: readonly IncludeCandidate[] | null;
    readonly candidatesState: 'loading' | 'failed' | 'done' | 'idle';
    readonly preview: PreviewResult | null;
    /** Content at the right end of the card's head. */
    readonly aside?: ComponentChildren | undefined;
}

export const rowId = (key: string): string => `rs-editor-row-${key === '' ? 'root' : key.replaceAll('.', '-')}`;

function ChipList({ chips }: { readonly chips: readonly Chip[] }): JSX.Element {
    return (
        <span class="rs-editor__chips">
            {chips.map((chip, index) => <span key={index} class={`rs-editor__chip rs-editor__chip--${chip.tone}`}>{chip.text}</span>)}
        </span>
    );
}

const MARKER_KEYS: { readonly [M in Exclude<RowMarker, null>]: string } = { empty: 'editor.row.empty', skipped: 'editor.row.skipped' };

interface RowProps {
    readonly row: Row;
    readonly marker: RowMarker;
    readonly onOpen: (path: SlotPath) => void;
    readonly onToggle: (key: string) => void;
    readonly onAdd: (row: Row) => void;
    readonly canChange: boolean;
}

function PropertyRow({ row, marker, onOpen, onToggle, onAdd, canChange }: RowProps): JSX.Element {
    const { translator: { t } } = usePage();
    const label = row.label.kind === 'name' ? row.label.name : t('editor.row.item', row.label.number);
    return (
        <li class="rs-editor__row" style={{ '--rs-depth': String(row.depth) }} data-kind={row.slot.value.kind}>
            <span class="rs-editor__toggle">
                {row.children === null ? null : (
                    <Button
                        variant="quiet"
                        icon={row.collapsed ? 'chevron_right' : 'expand_more'}
                        label={t(row.collapsed ? 'editor.row.expand' : 'editor.row.collapse', label)}
                        expanded={!row.collapsed}
                        onClick={() => onToggle(row.key)}
                    />
                )}
            </span>
            <button type="button" id={rowId(row.key)} class="rs-editor__row-open" onClick={() => onOpen(row.path)}>
                <span class={row.label.kind === 'name' ? 'rs-editor__name' : 'rs-editor__item'}>{label}</span>
                {row.slot.when === null ? null : (
                    <span class="rs-editor__chip rs-editor__chip--when">{t('editor.row.when', row.slot.when)}</span>
                )}
                <ChipList chips={row.chips} />
                {marker === null ? null : <Badge variant="warn">{t(MARKER_KEYS[marker])}</Badge>}
            </button>
            <span class="rs-editor__row-add">
                {row.addition === null || !canChange ? null : (
                    <Button
                        variant="quiet"
                        icon="add"
                        label={row.addition === 'item'
                            ? t('editor.row.addItem', label)
                            : row.label.kind === 'item' ? t('editor.row.addToItem', row.label.number) : t('editor.row.addTo', label)}
                        onClick={() => onAdd(row)}
                    />
                )}
            </span>
        </li>
    );
}

function BlankStart({ draft }: { readonly draft: Draft }): JSX.Element {
    const { translator: { t }, link } = usePage();
    const { store, typeSuggestions, canChange } = useEditorEnv();
    const library = link(linkTo(LIBRARY_ROUTE));
    return (
        <div class="rs-editor__start">
            <h3 class="rs-editor__start-title">{t('editor.properties.start.title')}</h3>
            <p class="rs-editor__muted">{t('editor.properties.start.text')}</p>
            <div class="rs-editor__start-types">
                {typeSuggestions.map((type) => (
                    <Button key={type} variant="secondary" disabled={!canChange} onClick={() => store.change((current) => withType(current, type))}>
                        {type}
                    </Button>
                ))}
            </div>
            {library === null || draft.isBlock ? null : (
                <LinkButton href={library} variant="tertiary">{t('editor.properties.start.library')}</LinkButton>
            )}
        </div>
    );
}

interface TreeViewProps extends PropertiesCardProps {
    readonly tree: Tree;
}

function TreeView({ tree, draft, catalog, catalogState, candidates, candidatesState, preview }: TreeViewProps): JSX.Element {
    const { translator: { t, tn } } = usePage();
    const { canChange } = useEditorEnv();
    const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(() => initiallyCollapsed(tree));
    const [target, setTarget] = useState<DrawerTarget | null>(null);
    const snippetNames = useMemo(() => new Map((candidates ?? []).map((candidate) => [candidate.code, candidate.name])), [candidates]);
    const rows = rowsOf(tree, collapsed, { words: { t, tn }, catalog, snippetNames });
    const output = preview?.output ?? null;
    const toggle = (key: string): void => {
        const next = new Set(collapsed);
        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }
        setCollapsed(next);
    };
    const add = (row: Row): void => {
        if (row.addition === null) {
            return;
        }
        if (collapsed.has(row.key)) {
            toggle(row.key);
        }
        setTarget({ kind: 'add', parent: row.path, addition: row.addition, returnId: rowId(row.key) });
    };
    const blank = tree.types.length === 0 && tree.properties.length === 0;
    const type = rootType(tree);
    return (
        <>
            <div class="rs-editor__root">
                <Icon name={draft.isBlock ? 'widgets' : 'data_object'} />
                <span class="rs-editor__root-label">
                    {tn(draft.isBlock ? 'editor.root.block' : 'editor.root.snippet', tree.properties.length)}
                </span>
                {type === '' ? null : <span class="rs-editor__chip rs-editor__chip--object">{t('editor.root.schema', type)}</span>}
                {canChange && !blank ? (
                    <Button
                        id={rowId('')}
                        variant="secondary"
                        icon="add"
                        onClick={() => setTarget({ kind: 'add', parent: [], addition: 'property', returnId: rowId('') })}
                    >
                        {t('editor.properties.add')}
                    </Button>
                ) : null}
            </div>
            {blank ? <BlankStart draft={draft} /> : null}
            {!blank && tree.properties.length === 0 ? (
                <CardState
                    kind="empty"
                    title={t('editor.properties.empty.title')}
                    text={t('editor.properties.empty.text')}
                    {...(canChange ? { action: { label: t('editor.properties.add'), onClick: () => setTarget({ kind: 'add', parent: [], addition: 'property', returnId: rowId('') }) } } : {})}
                />
            ) : null}
            {rows.length === 0 ? null : (
                <ul class="rs-editor__rows">
                    {rows.map((row) => (
                        <PropertyRow
                            key={row.key}
                            row={row}
                            marker={rowMarker(tree, row, output)}
                            onOpen={(path) => setTarget({ kind: 'edit', path, returnId: rowId(pathKey(path)) })}
                            onToggle={toggle}
                            onAdd={add}
                            canChange={canChange}
                        />
                    ))}
                </ul>
            )}
            {rows.length === 0 ? null : (
                <p class="rs-editor__legend">
                    {preview === null ? t('editor.properties.legendNoSample') : t('editor.properties.legend', preview.sample.label)}
                </p>
            )}
            {target === null ? null : (
                <PropertyDrawer
                    tree={tree}
                    target={target}
                    catalog={catalog}
                    catalogState={catalogState}
                    candidates={candidates}
                    candidatesState={candidatesState}
                    preview={preview}
                    onClose={() => setTarget(null)}
                />
            )}
        </>
    );
}

export function PropertiesCard(props: PropertiesCardProps): JSX.Element {
    const { translator: { t } } = usePage();
    const projection = useMemo(() => parseTemplate(props.draft.template), [props.draft.template]);
    return (
        <CardFrame icon="account_tree" title={t('editor.properties.title')} subtitle={t('editor.properties.subtitle')} aside={props.aside}>
            <div class="hui-card__body rs-editor__properties">
                {projection.kind === 'invalid' ? <Notice tone="error">{t('editor.properties.invalid')}</Notice> : null}
                {projection.kind === 'opaque' ? <Notice tone="warning">{t('editor.properties.opaque')}</Notice> : null}
                {projection.kind === 'tree' ? <TreeView {...props} tree={projection.tree} /> : null}
            </div>
        </CardFrame>
    );
}
