/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Putting a property back into a template, as a check's "Add …" asks: the property named by a dotted path
 * (`offers`, `offers.shippingDetails`) with the value the check carries, written as a template part, or an empty text
 * when the check has none — a property whose value is empty is left out of the output, so it changes nothing until
 * the admin gives it one.
 *
 * Each segment before the last names a property whose rows hold properties: an object, or a list over a store
 * collection whose items are objects. The property takes the place of one of the same name, or stands before the
 * first of `before` the object has, or ends the object. A template the builder cannot draw, or a path through
 * anything else, puts nothing back.
 */
import { readJson } from './json-document.ts';
import { parseTemplate, printTree, readSlot } from './template-projection.ts';
import type { Property, Slot, Tree, Value, ValueKind } from './template-tree.ts';

type Properties = readonly Property[];

const NONE = (): null => null;

/** The properties a value holds as rows, and the value with others in their place; null for a value without. */
const HOLDERS: { readonly [K in ValueKind]: (value: Value<K>) => { readonly properties: Properties; readonly with: (next: Properties) => Value } | null } = {
    field: NONE,
    fixed: NONE,
    text: NONE,
    object: (value) => ({ properties: value.properties, with: (properties) => ({ ...value, properties }) }),
    snippet: NONE,
    list: (value) => {
        const source = value.source;
        if (source.kind !== 'store' || source.item.kind !== 'object') {
            return null;
        }
        const item = source.item;
        return {
            properties: item.properties,
            with: (properties) => ({ ...value, source: { ...source, item: { ...item, properties } } }),
        };
    },
    code: NONE,
};

function holderOf<K extends ValueKind>(value: Value<K>): { readonly properties: Properties; readonly with: (next: Properties) => Value } | null {
    return HOLDERS[value.kind](value);
}

/** The properties with `property` in place: replacing its namesake, before the first of `before`, or at the end. */
function placed(properties: Properties, property: Property, before: readonly string[]): Properties {
    const same = properties.findIndex((candidate) => candidate.name === property.name);
    if (same >= 0) {
        return properties.map((candidate, index) => (index === same ? property : candidate));
    }
    const next = before
        .map((name) => properties.findIndex((candidate) => candidate.name === name))
        .find((index) => index >= 0);
    return next === undefined
        ? [...properties, property]
        : [...properties.slice(0, next), property, ...properties.slice(next)];
}

/** The properties with `property` put at the end of `parents`; null when a parent holds no properties. */
function putAt(properties: Properties, parents: readonly string[], property: Property, before: readonly string[]): Properties | null {
    const [head, ...rest] = parents;
    if (head === undefined) {
        return placed(properties, property, before);
    }
    const index = properties.findIndex((candidate) => candidate.name === head);
    const parent = properties[index];
    const holder = parent === undefined ? null : holderOf(parent.value);
    if (parent === undefined || holder === null) {
        return null;
    }
    const inner = putAt(holder.properties, rest, property, before);
    if (inner === null) {
        return null;
    }
    const updated: Property = { ...parent, value: holder.with(inner) };
    return properties.map((candidate, at) => (at === index ? updated : candidate));
}

const EMPTY: Slot = { when: null, value: { kind: 'fixed', literal: { type: 'text', value: '' } } };

/** The slot a template part reads as; an empty text when there is none or it is not JSON. */
function slotOf(value: string | null): Slot {
    if (value === null) {
        return EMPTY;
    }
    try {
        return readSlot(readJson(value));
    } catch {
        return EMPTY;
    }
}

/** The template with the property put back; null when it cannot be put there. */
export function restoredTemplate(template: string, path: string, value: string | null, before: readonly string[]): string | null {
    const projection = parseTemplate(template);
    const segments = path.split('.');
    const name = segments[segments.length - 1] ?? '';
    if (projection.kind !== 'tree' || name === '') {
        return null;
    }
    const property: Property = { name, ...slotOf(value) };
    const properties = putAt(projection.tree.properties, segments.slice(0, -1), property, before);
    if (properties === null) {
        return null;
    }
    const tree: Tree = { ...projection.tree, properties };
    return printTree(tree);
}
