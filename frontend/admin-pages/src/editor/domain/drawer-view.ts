/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the property drawer decides before anything reaches the template: the value a property starts with when its
 * kind of value changes, whether a value is complete enough to print, whether a name may be a property's, which
 * catalog fields the picker lists, the properties suggested for an object, and the field a property's name brings.
 */
import { readJson, JSON_NULL, JsonSyntaxError, jsonText } from './json-document.ts';
import { DIRECTIVES, type Value, type ValueKind } from './template-tree.ts';
import type { FieldCatalog, FieldGroup, Suggestion, ValueDefaults } from './model.ts';

const codeOf = (source: string): Value<'code'> => {
    try {
        return { kind: 'code', json: readJson(source) };
    } catch (error) {
        if (error instanceof JsonSyntaxError) {
            return { kind: 'code', json: JSON_NULL };
        }
        throw error;
    }
};

const fieldPathOf = (value: Value): string => (value.kind === 'field' ? value.path : '');

const STARTING: { readonly [K in ValueKind]: (previous: Value, defaults: ValueDefaults) => Value<K> } = {
    field: (previous) => ({ kind: 'field', path: fieldPathOf(previous), filters: [], fallback: null }),
    fixed: () => ({ kind: 'fixed', literal: jsonText('') }),
    text: (_previous, defaults) => ({ kind: 'text', text: defaults.text }),
    object: () => ({ kind: 'object', types: [], properties: [] }),
    snippet: () => ({ kind: 'snippet', code: '' }),
    list: (_previous, defaults) => ({
        kind: 'list',
        source: {
            kind: 'store',
            each: defaults.list.each,
            as: null,
            limit: null,
            item: { kind: 'field', path: defaults.list.item, filters: [], fallback: null },
        },
    }),
    code: (_previous, defaults) => codeOf(defaults.code),
};

/** The value a property starts with when its kind changes to `kind`; the same kind keeps the value as it is. */
export function valueOfKind(kind: ValueKind, previous: Value, defaults: ValueDefaults): Value {
    return previous.kind === kind ? previous : STARTING[kind](previous, defaults);
}

const COMPLETE: { readonly [K in ValueKind]: (value: Value<K>) => boolean } = {
    field: (value) => value.path !== '',
    fixed: () => true,
    text: () => true,
    object: () => true,
    snippet: (value) => value.code !== '',
    list: (value) => value.source.kind === 'items' || value.source.each !== '',
    code: () => true,
};

/** Whether a value can be printed into the template: a field needs its path, a snippet its code, a list its source. */
export function isComplete<K extends ValueKind>(value: Value<K>): boolean {
    return COMPLETE[value.kind](value);
}

const NAME = /^[A-Za-z][A-Za-z0-9_]*$/;

/** Whether a property may take a name: a schema.org-style name, not a directive, not one its object already has. */
export const isUsableName = (name: string, taken: readonly string[]): boolean =>
    NAME.test(name) && !DIRECTIVES.has(name) && !taken.includes(name);

/**
 * The catalog's fields the picker lists for a query and the kinds of page set: matched by label, attribute code or
 * path, without case; a field of no kind in particular is listed for every kind. A group named as a list's items
 * (`image`, `review`) is listed only inside that list, so `itemNames` names the lists the property is inside.
 */
export function pickableGroups(
    catalog: FieldCatalog,
    query: string,
    kinds: readonly string[],
    itemNames: ReadonlySet<string>,
): readonly FieldGroup[] {
    const needle = query.trim().toLowerCase();
    const listItems = new Set(catalog.lists.map((list) => list.itemName));
    return catalog.groups
        .filter((group) => !listItems.has(group.code) || itemNames.has(group.code))
        .map((group) => ({
            ...group,
            fields: group.fields.filter((field) =>
                (field.pageKinds.length === 0 || field.pageKinds.some((kind) => kinds.includes(kind)))
                && (needle === ''
                    || field.label.toLowerCase().includes(needle)
                    || field.path.toLowerCase().includes(needle)
                    || (field.attributeCode ?? '').toLowerCase().includes(needle))),
        }))
        .filter((group) => group.fields.length > 0);
}

/** The groups a condition may test: every group but a list's items. */
export function conditionGroups(catalog: FieldCatalog, kinds: readonly string[]): readonly FieldGroup[] {
    return pickableGroups(catalog, '', kinds, new Set());
}

/** The properties suggested for an object of `type` at `objectPath` under the root type, less those it has. */
export function suggestionsFor(
    catalog: FieldCatalog | null,
    rootType: string,
    objectPath: string,
    type: string,
    taken: readonly string[],
): readonly Suggestion[] {
    const byType = catalog?.suggestions.get(rootType)?.get(objectPath)
        ?? catalog?.suggestions.get(type)?.get('')
        ?? [];
    return byType.filter((suggestion) => !taken.includes(suggestion.name));
}

/**
 * The value once a property of an object of `type` is renamed from `before` to `name`: a field value that is empty,
 * or still holds the field the earlier name brought, takes the field the new name brings (none when it brings none);
 * a field picked by hand, and every other kind of value, stays as it is.
 */
export function namedValue(catalog: FieldCatalog | null, type: string, before: string, name: string, value: Value): Value {
    if (value.kind !== 'field') {
        return value;
    }
    const fields = catalog?.namedFields.get(type);
    const brought = fields?.get(before) ?? '';
    if (value.path !== '' && value.path !== brought) {
        return value;
    }
    const path = fields?.get(name) ?? '';
    return path === value.path ? value : { kind: 'field', path, filters: [], fallback: null };
}
