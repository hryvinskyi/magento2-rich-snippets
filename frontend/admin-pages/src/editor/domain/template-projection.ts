/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reading a template into the builder's tree and printing a tree back into a template, so that what the builder
 * prints is what the template engine reads.
 *
 * Reading, for each value:
 * - a string that is one field with its filters is a **field**; a string with no expression is a **fixed** value; any
 *   other string with an expression is **text with fields**; a number or `true`/`false` is a fixed value;
 * - an object `{"@include": code}` is **another snippet**;
 * - an object with `@each` is a **list** over a store collection: `@as` names its items, `@limit` caps them, and
 *   the items are `@value`, or the object's own `@type` and keys;
 * - a JSON array is a **list** of items entered here;
 * - any other object is an **object**, its `@type` a string or a list of strings;
 * - `@if` holding a bare field path is the field the value is output only when; on a value that is not an object
 *   or a snippet it is written `{"@if": field, "@value": value}`.
 *
 * Everything else is kept as **code**, as written: `null`, an `@if` that is not a bare path, an `@if` beside
 * `@each` (it filters the items, which the builder does not draw), a directive in a place the engine refuses it, a
 * string the engine cannot split. A root that is not an object, or holds a directive, cannot be drawn at all.
 *
 * Printing writes two spaces per level and the keys in the order `@if`, `@context`, `@type`, then the properties;
 * a list writes `@each`, `@as`, `@limit`, then its items. Another snippet is written on one line,
 * `{ "@include": "code" }`. A template printed from a tree reads back into the same tree, and prints the same.
 */
import {
    indent,
    jsonText,
    memberOf,
    printJson,
    quoted,
    readJson,
    type Json,
    type JsonEntry,
    type JsonRecord,
} from './json-document.ts';
import { escapedText, fieldOf, hasExpression, literalOf, printField, splitText } from './template-text.ts';
import {
    DIRECTIVES,
    type ListSource,
    type Projection,
    type Property,
    type Slot,
    type Tree,
    type Value,
    type ValueKind,
} from './template-tree.ts';

const BARE_PATH = /^[A-Za-z_][A-Za-z0-9_]*(?:\.(?:[A-Za-z_][A-Za-z0-9_]*|\d+))*$/;
const KEYWORDS = new Set(['and', 'or', 'not', 'true', 'false', 'null']);
const ALIAS = /^[A-Za-z_][A-Za-z0-9_]*$/;

/** Whether a string is a field path the builder can show as "has a value". */
export const isBarePath = (value: string): boolean =>
    BARE_PATH.test(value) && !KEYWORDS.has(value.split('.')[0] ?? '');

const code = (json: Json): Slot => ({ when: null, value: { kind: 'code', json } });

const textOf = (json: Json | undefined): string | null => (json !== undefined && json.type === 'text' ? json.value : null);

/** The types an `@type` holds; null when it is neither a string nor a list of strings. */
function typesOf(json: Json | undefined): readonly string[] | null {
    if (json === undefined) {
        return [];
    }
    if (json.type === 'text') {
        return [json.value];
    }
    if (json.type !== 'list' || json.items.length < 2) {
        return null;
    }
    const types = json.items.map((item) => (item.type === 'text' ? item.value : null));
    return types.every((type): type is string => type !== null) ? types : null;
}

function readString(value: string, json: Json): Slot {
    const parts = splitText(value);
    if (parts === null) {
        return code(json);
    }
    if (!hasExpression(parts)) {
        return { when: null, value: { kind: 'fixed', literal: jsonText(literalOf(parts)) } };
    }
    const only = parts.length === 1 ? parts[0] : undefined;
    const field = only !== undefined && only.kind === 'expression' ? fieldOf(only.source) : null;
    return field === null
        ? { when: null, value: { kind: 'text', text: value } }
        : { when: null, value: { kind: 'field', path: field.path, filters: field.filters, fallback: field.fallback } };
}

function readProperties(entries: readonly JsonEntry[]): readonly Property[] {
    return entries.map(([name, json]) => ({ name, ...readSlot(json) }));
}

/** The keys of a record that are not directives. */
const plainEntries = (record: JsonRecord): readonly JsonEntry[] => record.entries.filter(([key]) => !DIRECTIVES.has(key));

const hasOnly = (record: JsonRecord, allowed: ReadonlySet<string>): boolean => record.entries.every(([key]) => allowed.has(key));

/** The field of an `@if`: absent → null; a bare path → the path; anything else → undefined (not drawable). */
function whenOf(record: JsonRecord): string | null | undefined {
    const json = memberOf(record, '@if');
    if (json === undefined) {
        return null;
    }
    const path = textOf(json);
    return path !== null && isBarePath(path) ? path : undefined;
}

function readObject(record: JsonRecord, when: string | null): Slot {
    const types = typesOf(memberOf(record, '@type'));
    if (types === null) {
        return code(record);
    }
    const properties = readProperties(plainEntries(record).filter(([key]) => key !== '@type'));
    return { when, value: { kind: 'object', types, properties } };
}

function readList(record: JsonRecord): Slot {
    const each = textOf(memberOf(record, '@each'));
    const alias = memberOf(record, '@as');
    const limit = memberOf(record, '@limit');
    const as = alias === undefined ? null : textOf(alias);
    const okLimit = limit === undefined || (limit.type === 'number' && /^[1-9]\d{0,8}$/.test(limit.raw));
    if (each === null || !isBarePath(each) || memberOf(record, '@if') !== undefined || !okLimit
        || (alias !== undefined && (as === null || !ALIAS.test(as)))) {
        return code(record);
    }
    const valueJson = memberOf(record, '@value');
    let item: Slot;
    if (valueJson === undefined) {
        item = readObject(record, null);
    } else if (plainEntries(record).length === 0) {
        item = readSlot(valueJson);
    } else {
        return code(record);
    }
    if (item.when !== null) {
        return code(record);
    }
    const source: ListSource = {
        kind: 'store',
        each,
        as,
        limit: limit !== undefined && limit.type === 'number' ? Number(limit.raw) : null,
        item: item.value,
    };
    return { when: null, value: { kind: 'list', source } };
}

const INCLUDE_KEYS = new Set(['@include', '@if']);
const VALUE_KEYS = new Set(['@value', '@if']);

function readRecord(record: JsonRecord): Slot {
    const when = whenOf(record);
    if (when === undefined) {
        return code(record);
    }
    if (memberOf(record, '@include') !== undefined) {
        const snippet = textOf(memberOf(record, '@include'));
        return snippet === null || !hasOnly(record, INCLUDE_KEYS) ? code(record) : { when, value: { kind: 'snippet', code: snippet } };
    }
    if (memberOf(record, '@each') !== undefined) {
        return readList(record);
    }
    if (memberOf(record, '@as') !== undefined || memberOf(record, '@limit') !== undefined) {
        return code(record);
    }
    const inner = memberOf(record, '@value');
    if (inner !== undefined) {
        if (!hasOnly(record, VALUE_KEYS)) {
            return code(record);
        }
        const slot = readSlot(inner);
        return when !== null && slot.when !== null ? code(record) : { when: when ?? slot.when, value: slot.value };
    }
    return readObject(record, when);
}

const READERS: { readonly [K in Json['type']]: (json: Json<K>) => Slot } = {
    text: (json) => readString(json.value, json),
    number: (json) => ({ when: null, value: { kind: 'fixed', literal: json } }),
    flag: (json) => ({ when: null, value: { kind: 'fixed', literal: json } }),
    null: (json) => code(json),
    list: (json) => ({ when: null, value: { kind: 'list', source: { kind: 'items', items: json.items.map((item) => readSlot(item)) } } }),
    record: (json) => readRecord(json),
};

/** The slot a JSON value of a template is drawn as. */
export function readSlot<K extends Json['type']>(json: Json<K>): Slot {
    return READERS[json.type](json);
}

/** The builder's view of a template. */
export function parseTemplate(source: string): Projection {
    let json: Json;
    try {
        json = readJson(source);
    } catch {
        return { kind: 'invalid' };
    }
    if (json.type !== 'record' || json.entries.some(([key]) => DIRECTIVES.has(key))) {
        return { kind: 'opaque' };
    }
    const types = typesOf(memberOf(json, '@type'));
    if (types === null) {
        return { kind: 'opaque' };
    }
    const tree: Tree = {
        context: memberOf(json, '@context') ?? null,
        types,
        properties: readProperties(json.entries.filter(([key]) => key !== '@context' && key !== '@type')),
    };
    return { kind: 'tree', tree };
}

/** A record written member by member, each member's value already printed. */
function printMembers(members: readonly (readonly [string, string])[], depth: number): string {
    if (members.length === 0) {
        return '{}';
    }
    return `{\n${members.map(([key, value]) => `${indent(depth + 1)}${quoted(key)}: ${value}`).join(',\n')}\n${indent(depth)}}`;
}

/** The `@type` member: none for no type, a string for one, a list on one line for several. */
const typeMember = (types: readonly string[]): readonly (readonly [string, string])[] => {
    if (types.length === 0) {
        return [];
    }
    return [['@type', types.length === 1 ? quoted(types[0] ?? '') : `[${types.map((type) => quoted(type)).join(', ')}]`]];
};

const propertyMembers = (properties: readonly Property[], depth: number): readonly (readonly [string, string])[] =>
    properties.map((property) => [property.name, printSlot(property, depth + 1)]);

const whenMember = (when: string | null): readonly (readonly [string, string])[] => (when === null ? [] : [['@if', quoted(when)]]);

function printList(source: ListSource, depth: number): string {
    if (source.kind === 'items') {
        return source.items.length === 0
            ? '[]'
            : `[\n${source.items.map((item) => indent(depth + 1) + printSlot(item, depth + 1)).join(',\n')}\n${indent(depth)}]`;
    }
    const head: (readonly [string, string])[] = [['@each', quoted(source.each)]];
    if (source.as !== null) {
        head.push(['@as', quoted(source.as)]);
    }
    if (source.limit !== null) {
        head.push(['@limit', String(source.limit)]);
    }
    const item = source.item;
    const rest = item.kind === 'object'
        ? [...typeMember(item.types), ...propertyMembers(item.properties, depth)]
        : [['@value', printValue(item, depth + 1)] as const];
    return printMembers([...head, ...rest], depth);
}

const PRINTERS: { readonly [K in ValueKind]: (value: Value<K>, depth: number) => string } = {
    field: (value) => quoted(printField(value)),
    fixed: (value) => (value.literal.type === 'text' ? quoted(escapedText(value.literal.value)) : printJson(value.literal)),
    text: (value) => quoted(value.text),
    object: (value, depth) => printMembers([...typeMember(value.types), ...propertyMembers(value.properties, depth)], depth),
    snippet: (value) => `{ "@include": ${quoted(value.code)} }`,
    list: (value, depth) => printList(value.source, depth),
    code: (value, depth) => printJson(value.json, depth),
};

/** A value without a condition, as a template writes it at `depth`. */
export function printValue<K extends ValueKind>(value: Value<K>, depth: number): string {
    return PRINTERS[value.kind](value, depth);
}

const wrapped = (value: Value, when: string, depth: number): string =>
    printMembers([...whenMember(when), ['@value', printValue(value, depth + 1)]], depth);

const GUARDED: { readonly [K in ValueKind]: (value: Value<K>, when: string, depth: number) => string } = {
    field: wrapped,
    fixed: wrapped,
    text: wrapped,
    object: (value, when, depth) =>
        printMembers([...whenMember(when), ...typeMember(value.types), ...propertyMembers(value.properties, depth)], depth),
    snippet: (value, when) => `{ "@if": ${quoted(when)}, "@include": ${quoted(value.code)} }`,
    list: wrapped,
    code: wrapped,
};

function printGuarded<K extends ValueKind>(value: Value<K>, when: string, depth: number): string {
    return GUARDED[value.kind](value, when, depth);
}

/** A slot as a template writes it at `depth`: an object or a snippet carries its `@if`, any other value is wrapped. */
export function printSlot(slot: Slot, depth: number): string {
    return slot.when === null ? printValue(slot.value, depth) : printGuarded(slot.value, slot.when, depth);
}

/** A tree as a template. */
export function printTree(tree: Tree): string {
    const context: readonly (readonly [string, string])[] = tree.context === null ? [] : [['@context', printJson(tree.context, 1)]];
    return printMembers([...context, ...typeMember(tree.types), ...propertyMembers(tree.properties, 0)], 0);
}
