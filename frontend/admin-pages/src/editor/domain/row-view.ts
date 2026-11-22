/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The builder's rows: the tree flattened in the order the template writes it, each row with its depth, its label
 * (the property's name, or the item's number), the chips that say where its value comes from, whether rows can be
 * added under it, and whether it is collapsed.
 *
 * A field is named by its group and label in the catalog ("Product › Name"), or by its path while the catalog is not
 * loaded or lacks it. Wording comes from the caller's words; this module holds none.
 */
import { printJson } from './json-document.ts';
import type { FieldFilter } from './template-text.ts';
import { childrenOf, firstType, isProperty, type ListSource, type Slot, type Tree, type Value, type ValueKind } from './template-tree.ts';
import type { SlotPath } from './tree-edits.ts';
import type { CatalogField, FieldCatalog } from './model.ts';

/** The page's wording, as the kit's translator gives it. */
export interface Words {
    t(key: string, ...params: readonly (string | number)[]): string;
    tn(key: string, count: number, ...params: readonly (string | number)[]): string;
}

export type ChipTone = ValueKind | 'filter' | 'fallback' | 'muted';

export interface Chip {
    readonly tone: ChipTone;
    readonly text: string;
}

export type RowLabel = { readonly kind: 'name'; readonly name: string } | { readonly kind: 'item'; readonly number: number };

/** What can be added under a row: a named property, an item, or nothing. */
export type RowAddition = 'property' | 'item' | null;

export interface Row {
    readonly path: SlotPath;
    readonly key: string;
    readonly depth: number;
    readonly label: RowLabel;
    readonly slot: Slot;
    readonly chips: readonly Chip[];
    /** How many rows are under it; null for a value with no rows under it. */
    readonly children: number | null;
    readonly addition: RowAddition;
    readonly collapsed: boolean;
}

/** What the chips are worded from beside the words. */
export interface ChipContext {
    readonly words: Words;
    readonly catalog: FieldCatalog | null;
    /** Snippet names by code, for the snippets the edited one may include. */
    readonly snippetNames: ReadonlyMap<string, string>;
}

/** A path as one string, for sets of collapsed rows and for element ids. */
export const pathKey = (path: SlotPath): string => path.join('.');

/** The catalog's field at a path, with the label of the group it is shown under. */
export function catalogField(catalog: FieldCatalog | null, path: string): { readonly field: CatalogField; readonly group: string } | null {
    const root = path.split('.')[0] ?? '';
    for (const group of catalog?.groups ?? []) {
        const field = group.fields.find((item) => item.path === path);
        if (field !== undefined) {
            const named = catalog?.groups.find((item) => item.code === root);
            return { field, group: named?.label ?? group.label };
        }
    }
    return null;
}

/** A field as a person reads it: "Group › Label", or its path when the catalog does not list it. */
export function fieldName(catalog: FieldCatalog | null, path: string): string {
    const found = catalogField(catalog, path);
    return found === null ? path : `${found.group} › ${found.field.label}`;
}

const FILTER_WORDS: Readonly<Record<string, (filter: FieldFilter, words: Words) => string>> = {
    plain: (_filter, words) => words.t('editor.filter.plain'),
    limit: (filter, words) => words.t('editor.filter.limit', filter.args[0] ?? ''),
};

const filterChip = (filter: FieldFilter, words: Words): Chip => ({
    tone: 'filter',
    text: FILTER_WORDS[filter.name]?.(filter, words) ?? [filter.name, ...filter.args].join(':'),
});

const lineCount = (text: string): number => text.split('\n').length;

const listChips = (source: ListSource, context: ChipContext): readonly Chip[] => {
    const { words, catalog } = context;
    if (source.kind === 'items') {
        return [
            { tone: 'list', text: words.tn('editor.chip.items', source.items.length) },
            { tone: 'muted', text: words.t('editor.chip.enteredHere') },
        ];
    }
    const list = catalog?.lists.find((item) => item.path === source.each);
    const chips: Chip[] = [{ tone: 'list', text: words.t('editor.chip.oneForEach', list?.label ?? source.each) }];
    if (source.as !== null) {
        chips.push({ tone: 'muted', text: words.t('editor.chip.as', source.as) });
    }
    if (source.limit !== null) {
        chips.push({ tone: 'filter', text: words.t('editor.chip.newest', source.limit) });
    }
    if (source.item.kind !== 'object') {
        chips.push(...valueChips(source.item, context));
    }
    return chips;
};

const CHIPS: { readonly [K in ValueKind]: (value: Value<K>, context: ChipContext) => readonly Chip[] } = {
    field: (value, { words, catalog }) => [
        { tone: 'field', text: value.path === '' ? words.t('editor.chip.pickField') : fieldName(catalog, value.path) },
        ...value.filters.map((filter) => filterChip(filter, words)),
        ...(value.fallback === null ? [] : [{ tone: 'fallback' as const, text: words.t('editor.chip.else', value.fallback) }]),
    ],
    fixed: (value, { words }) => {
        const text = value.literal.type === 'text' ? value.literal.value : value.literal.type === 'number' ? value.literal.raw : String(value.literal.value);
        const chips: Chip[] = [{ tone: 'fixed', text: words.t('editor.chip.quoted', text) }];
        if (text.startsWith('https://schema.org/')) {
            chips.push({ tone: 'muted', text: words.t('editor.chip.schemaValue') });
        }
        return chips;
    },
    text: (value, { words }) => [
        { tone: 'text', text: value.text === '' ? words.t('editor.chip.nothingTyped') : words.t('editor.chip.quoted', value.text) },
    ],
    object: (value, { words }) => [
        { tone: 'object', text: value.types.length === 0 ? words.t('editor.chip.noType') : value.types.join(', ') },
        { tone: 'muted', text: words.tn('editor.chip.properties', value.properties.length) },
    ],
    snippet: (value, { words, snippetNames }) => [
        { tone: 'snippet', text: snippetNames.get(value.code) ?? value.code },
        { tone: 'muted', text: words.t('editor.chip.snippet') },
    ],
    list: (value, context) => listChips(value.source, context),
    code: (value, { words }) => [
        { tone: 'code', text: words.t('editor.chip.customCode') },
        { tone: 'muted', text: words.tn('editor.chip.lines', lineCount(printJson(value.json, 0))) },
    ],
};

/** The chips that say where a value comes from. */
export function valueChips<K extends ValueKind>(value: Value<K>, context: ChipContext): readonly Chip[] {
    return CHIPS[value.kind](value, context);
}

const additionOf = (value: Value): RowAddition => {
    if (value.kind === 'object') {
        return 'property';
    }
    if (value.kind !== 'list') {
        return null;
    }
    if (value.source.kind === 'items') {
        return 'item';
    }
    return value.source.item.kind === 'object' ? 'property' : null;
};

/**
 * The rows collapsed when the editor opens: store lists whose items are objects, and each item entered in the editor
 * after the first that has rows under it.
 */
export function initiallyCollapsed(tree: Tree): ReadonlySet<string> {
    const collapsed = new Set<string>();
    const walk = (slots: readonly Slot[], parent: SlotPath, itemsList: boolean): void => {
        slots.forEach((slot, index) => {
            const path = [...parent, index];
            const children = childrenOf(slot.value);
            const storeObjects = slot.value.kind === 'list' && slot.value.source.kind === 'store' && slot.value.source.item.kind === 'object';
            if (children !== null && (storeObjects || (itemsList && index > 0))) {
                collapsed.add(pathKey(path));
            }
            if (children !== null) {
                walk(children, path, slot.value.kind === 'list' && slot.value.source.kind === 'items');
            }
        });
    };
    walk(tree.properties, [], false);
    return collapsed;
}

/** The rows drawn for a tree; the rows under a collapsed row are left out. */
export function rowsOf(tree: Tree, collapsed: ReadonlySet<string>, context: ChipContext): readonly Row[] {
    const rows: Row[] = [];
    const walk = (slots: readonly Slot[], parent: SlotPath, depth: number): void => {
        slots.forEach((slot, index) => {
            const path = [...parent, index];
            const key = pathKey(path);
            const children = childrenOf(slot.value);
            const isCollapsed = children !== null && collapsed.has(key);
            rows.push({
                path,
                key,
                depth,
                label: isProperty(slot) ? { kind: 'name', name: slot.name } : { kind: 'item', number: index + 1 },
                slot,
                chips: valueChips(slot.value, context),
                children: children === null ? null : children.length,
                addition: additionOf(slot.value),
                collapsed: isCollapsed,
            });
            if (children !== null && !isCollapsed) {
                walk(children, path, depth + 1);
            }
        });
    };
    walk(tree.properties, [], 0);
    return rows;
}

/** The root's type as the root bar shows it. */
export const rootType = (tree: Tree): string => firstType(tree.types);

/** What a preview output says about a row: empty there, skipped there by its condition, or nothing to say. */
export type RowMarker = 'empty' | 'skipped' | null;

const isObject = (value: unknown): value is Readonly<Record<string, unknown>> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const memberOf = (value: unknown, name: string): unknown => (isObject(value) ? value[name] : undefined);

/** What the preview output holds for a slot: its value, nothing, or null when the output cannot tell. */
export type SlotOutput = { readonly kind: 'value'; readonly value: unknown } | { readonly kind: 'absent' };

/**
 * What the preview's output holds for the named slot at `path`: the slot's object is read by the names on its path; a
 * slot inside a list, or one whose object was not output, gives null, since the output does not say which item it
 * would be.
 */
export function slotOutput(tree: Tree, path: SlotPath, output: Readonly<Record<string, unknown>> | null): SlotOutput | null {
    if (output === null) {
        return null;
    }
    let object: unknown = output;
    let slots: readonly Slot[] | null = tree.properties;
    let slot: Slot | undefined;
    for (const [depth, index] of path.entries()) {
        slot = slots?.[index];
        if (slot === undefined || !isProperty(slot)) {
            return null;
        }
        if (depth < path.length - 1) {
            if (slot.value.kind !== 'object') {
                return null;
            }
            object = memberOf(object, slot.name);
            if (object === undefined) {
                return null;
            }
            slots = slot.value.properties;
        }
    }
    if (slot === undefined || !isProperty(slot)) {
        return null;
    }
    const value = memberOf(object, slot.name);
    return value === undefined ? { kind: 'absent' } : { kind: 'value', value };
}

/** The marker of a row on the preview's output: empty there, or skipped there when it has a condition. */
export function rowMarker(tree: Tree, row: Row, output: Readonly<Record<string, unknown>> | null): RowMarker {
    const found = slotOutput(tree, row.path, output);
    if (found === null || found.kind === 'value') {
        return null;
    }
    return row.slot.when === null ? 'empty' : 'skipped';
}
