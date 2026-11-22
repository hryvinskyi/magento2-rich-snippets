/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Edits of the builder's tree. A slot is found by its path: the index of a root property, then the index among the
 * rows under each value on the way down (see `childrenOf`). Every edit answers a new tree and leaves the old one as it
 * was, so the editor prints the new one into the template and keeps the old one for comparison.
 *
 * A path that leads nowhere, or a slot without a name put among an object's properties, is a fault of the caller and
 * throws: the builder only builds paths from the rows it drew.
 */
import { childrenOf, isProperty, type ListSource, type Property, type Slot, type Tree, type Value, type ValueKind } from './template-tree.ts';

/** The indices leading to a slot; the first is a root property's. */
export type SlotPath = readonly number[];

export class SlotPathError extends Error {
    constructor(path: SlotPath) {
        super(`No slot of the template is at [${path.join(', ')}].`);
        this.name = 'SlotPathError';
    }
}

const properties = (slots: readonly Slot[], path: SlotPath): readonly Property[] =>
    slots.map((slot) => {
        if (!isProperty(slot)) {
            throw new SlotPathError(path);
        }
        return slot;
    });

const listWith = (source: ListSource, children: readonly Slot[], path: SlotPath): ListSource => {
    if (source.kind === 'items') {
        return { ...source, items: children };
    }
    if (source.item.kind !== 'object') {
        throw new SlotPathError(path);
    }
    return { ...source, item: { ...source.item, properties: properties(children, path) } };
};

const NO_CHILDREN = (_value: Value, _children: readonly Slot[], path: SlotPath): never => {
    throw new SlotPathError(path);
};

const WITH_CHILDREN: { readonly [K in ValueKind]: (value: Value<K>, children: readonly Slot[], path: SlotPath) => Value<K> } = {
    field: NO_CHILDREN,
    fixed: NO_CHILDREN,
    text: NO_CHILDREN,
    object: (value, children, path) => ({ ...value, properties: properties(children, path) }),
    snippet: NO_CHILDREN,
    list: (value, children, path) => ({ ...value, source: listWith(value.source, children, path) }),
    code: NO_CHILDREN,
};

function withChildren<K extends ValueKind>(value: Value<K>, children: readonly Slot[], path: SlotPath): Value<K> {
    return WITH_CHILDREN[value.kind](value, children, path);
}

/** The slot at a path. */
export function slotAt(tree: Tree, path: SlotPath): Slot {
    let slots: readonly Slot[] | null = tree.properties;
    let slot: Slot | undefined;
    for (const index of path) {
        slot = slots?.[index];
        if (slot === undefined) {
            throw new SlotPathError(path);
        }
        slots = childrenOf(slot.value);
    }
    if (slot === undefined) {
        throw new SlotPathError(path);
    }
    return slot;
}

/** The rows under a slot, or the root's properties for the empty path. */
export function childrenAt(tree: Tree, path: SlotPath): readonly Slot[] {
    if (path.length === 0) {
        return tree.properties;
    }
    const children = childrenOf(slotAt(tree, path).value);
    if (children === null) {
        throw new SlotPathError(path);
    }
    return children;
}

/** The tree with the slots under the slot at `parent` (the root's for the empty path) changed by `change`. */
function changeChildren(tree: Tree, parent: SlotPath, change: (slots: readonly Slot[]) => readonly Slot[]): Tree {
    const walk = (slots: readonly Slot[], depth: number): readonly Slot[] => {
        if (depth === parent.length) {
            return change(slots);
        }
        const index = parent[depth] ?? -1;
        const slot = slots[index];
        if (slot === undefined) {
            throw new SlotPathError(parent);
        }
        const children = childrenOf(slot.value);
        if (children === null) {
            throw new SlotPathError(parent);
        }
        const next: Slot = { ...slot, value: withChildren(slot.value, walk(children, depth + 1), parent) };
        return slots.map((item, at) => (at === index ? next : item));
    };
    return { ...tree, properties: properties(walk(tree.properties, 0), parent) };
}

const parentOf = (path: SlotPath): SlotPath => path.slice(0, -1);

const indexOf = (path: SlotPath): number => {
    const index = path[path.length - 1];
    if (index === undefined) {
        throw new SlotPathError(path);
    }
    return index;
};

/** The tree with the slot at `path` replaced. */
export function replaceSlot(tree: Tree, path: SlotPath, slot: Slot | Property): Tree {
    const index = indexOf(path);
    return changeChildren(tree, parentOf(path), (slots) => {
        if (slots[index] === undefined) {
            throw new SlotPathError(path);
        }
        return slots.map((item, at) => (at === index ? slot : item));
    });
}

/** The tree without the slot at `path`. */
export function removeSlot(tree: Tree, path: SlotPath): Tree {
    const index = indexOf(path);
    return changeChildren(tree, parentOf(path), (slots) => {
        if (slots[index] === undefined) {
            throw new SlotPathError(path);
        }
        return slots.filter((_item, at) => at !== index);
    });
}

/** The tree with `slot` added after the last row under `parent` (the root for the empty path), and its path. */
export function appendSlot(tree: Tree, parent: SlotPath, slot: Slot | Property): { readonly tree: Tree; readonly path: SlotPath } {
    let index = 0;
    const next = changeChildren(tree, parent, (slots) => {
        index = slots.length;
        return [...slots, slot];
    });
    return { tree: next, path: [...parent, index] };
}

/** The tree with the root's type set; an empty type takes the type away. */
export const withRootType = (tree: Tree, type: string): Tree => ({ ...tree, types: type.trim() === '' ? [] : [type.trim()] });
