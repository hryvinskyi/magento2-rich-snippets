/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A snippet's template as the builder draws it: the root (its `@context`, its type, its properties) and, for each
 * property, where its value comes from — one of seven kinds of value — and the field it may be output only when.
 *
 * The template is the one source of truth: the tree is read from it (`parseTemplate`), every edit makes a new tree
 * that is printed back into a template (`printTree`), and a part the builder cannot draw stays as a `code` value
 * holding that part as written.
 */
import type { Json } from './json-document.ts';
import type { FieldFilter } from './template-text.ts';

/** A list's items: one per element of a store collection, or items entered in the editor. */
export type ListSource =
    | {
        readonly kind: 'store';
        /** The collection's path (`product.gallery`). */
        readonly each: string;
        /** The name the items are read under; null for the collection's own. */
        readonly as: string | null;
        /** At most this many items; null for all of them. */
        readonly limit: number | null;
        readonly item: Value;
    }
    | { readonly kind: 'items'; readonly items: readonly Slot[] };

/** A literal the builder shows as a fixed value. */
export type FixedLiteral = Json<'text' | 'number' | 'flag'>;

interface ValueParts {
    /** A store field, with its filters and the value used when it is empty. */
    field: { readonly path: string; readonly filters: readonly FieldFilter[]; readonly fallback: string | null };
    /** The same on every page. */
    fixed: { readonly literal: FixedLiteral };
    /** A sentence that mixes text and fields, as the template string writes it. */
    text: { readonly text: string };
    /** A thing with its own type and properties; `types` holds the `@type` (empty for none, several for a list). */
    object: { readonly types: readonly string[]; readonly properties: readonly Property[] };
    /** Another snippet, by code. */
    snippet: { readonly code: string };
    list: { readonly source: ListSource };
    /** A part of the template the builder leaves as written. */
    code: { readonly json: Json };
}

export type ValueKind = keyof ValueParts;

export const VALUE_KINDS: readonly ValueKind[] = Object.freeze(['field', 'fixed', 'text', 'object', 'snippet', 'list', 'code']);

/** One kind of value; `kind` says which. */
export type Value<K extends ValueKind = ValueKind> = { [P in K]: { readonly kind: P } & ValueParts[P] }[K];

/** A value and the field it is output only when; null when it is always output. */
export interface Slot {
    readonly when: string | null;
    readonly value: Value;
}

/** A named property of an object or of the root. */
export interface Property extends Slot {
    readonly name: string;
}

export interface Tree {
    /** The root's `@context` as written; null for a template without one (a building block). */
    readonly context: Json | null;
    /** The root's `@type`; empty for none. */
    readonly types: readonly string[];
    readonly properties: readonly Property[];
}

/** What the builder makes of a template: a tree, a root it cannot draw, or text that is not JSON. */
export type Projection =
    | { readonly kind: 'tree'; readonly tree: Tree }
    | { readonly kind: 'opaque' }
    | { readonly kind: 'invalid' };

/** The directive keys of the template language; any other `@` key is plain JSON-LD and passes through. */
export const DIRECTIVES: ReadonlySet<string> = new Set(['@if', '@each', '@as', '@limit', '@value', '@include']);

/** The keys the root itself holds. */
export const ROOT_KEYS: ReadonlySet<string> = new Set(['@context', '@type']);

/** The type a person sees for a list of types: its first entry; empty for none. */
export const firstType = (types: readonly string[]): string => types[0] ?? '';

const NONE = (): null => null;

const listChildren = (source: ListSource): readonly Slot[] | null => {
    if (source.kind === 'items') {
        return source.items;
    }
    return source.item.kind === 'object' ? source.item.properties : null;
};

const CHILDREN: { readonly [K in ValueKind]: (value: Value<K>) => readonly Slot[] | null } = {
    field: NONE,
    fixed: NONE,
    text: NONE,
    object: (value) => value.properties,
    snippet: NONE,
    list: (value) => listChildren(value.source),
    code: NONE,
};

/**
 * The slots drawn as rows under a value: an object's properties, the item properties of a store list whose items
 * are objects, the items entered in the editor; null for a value with no rows under it.
 */
export function childrenOf<K extends ValueKind>(value: Value<K>): readonly Slot[] | null {
    return CHILDREN[value.kind](value);
}

/** Whether a slot is a named property. */
export const isProperty = (slot: Slot): slot is Property => 'name' in slot;
