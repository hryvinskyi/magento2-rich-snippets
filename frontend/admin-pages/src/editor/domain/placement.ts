/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Where a snippet is output, as the Pages card changes it: the kinds of page picked, the conditions narrowing them,
 * the store views.
 *
 * Unpicking a kind takes away the conditions only its pages could be tested on, so the stored tree never holds a
 * condition no kind picked is about. Every store view is exclusive: picking it drops the single views, picking a
 * single view drops it, and unpicking the last single view picks it again.
 */
import type { ConditionField, PageKind } from './model.ts';

/** The store view id that stands for every store view. */
export const EVERY_STORE = 0;

/** The kinds picked after `code` is toggled, in the order the kinds are listed. */
export function toggledKinds(picked: readonly string[], code: string, kinds: readonly PageKind[]): readonly string[] {
    const next = new Set(picked);
    if (next.has(code)) {
        next.delete(code);
    } else {
        next.add(code);
    }
    return kinds.map((kind) => kind.code).filter((kind) => next.has(kind));
}

/** The store views picked after `id` is toggled. */
export function toggledStores(picked: readonly number[], id: number): readonly number[] {
    if (id === EVERY_STORE) {
        return [EVERY_STORE];
    }
    const single = picked.filter((store) => store !== EVERY_STORE);
    const next = single.includes(id) ? single.filter((store) => store !== id) : [...single, id];
    return next.length === 0 ? [EVERY_STORE] : next;
}

/** The condition fields the kinds picked may be tested on. */
export function conditionCodes(picked: readonly string[], vocabulary: ReadonlyMap<string, readonly ConditionField[]>): ReadonlySet<string> {
    return new Set(picked.flatMap((kind) => (vocabulary.get(kind) ?? []).map((field) => field.code)));
}

/** Whether any kind picked may be narrowed by a condition. */
export const canNarrow = (picked: readonly string[], vocabulary: ReadonlyMap<string, readonly ConditionField[]>): boolean =>
    conditionCodes(picked, vocabulary).size > 0;

type Fields = Readonly<Record<string, unknown>>;

const isFields = (value: unknown): value is Fields => typeof value === 'object' && value !== null && !Array.isArray(value);

/** A condition node without the leaves `keep` refuses; a group is kept, emptied if need be. */
function pruned(node: Fields, combine: string, keep: ReadonlySet<string>): Fields | null {
    if (node['type'] !== combine) {
        return typeof node['type'] === 'string' && keep.has(node['type']) ? node : null;
    }
    const children = Array.isArray(node['conditions']) ? node['conditions'] : [];
    return {
        ...node,
        conditions: children.flatMap((child: unknown) => {
            const kept = isFields(child) ? pruned(child, combine, keep) : null;
            return kept === null ? [] : [kept];
        }),
    };
}

/**
 * The condition tree without the conditions on fields outside `keep`; empty when nothing is left to test. A tree
 * that is not JSON is left as it is: the server refuses it on save with its own words.
 */
export function prunedConditions(conditions: string, combine: string, keep: ReadonlySet<string>): string {
    if (conditions.trim() === '') {
        return '';
    }
    let root: unknown;
    try {
        root = JSON.parse(conditions);
    } catch {
        return conditions;
    }
    if (!isFields(root)) {
        return conditions;
    }
    const kept = pruned(root, combine, keep);
    if (kept === null || !hasLeaf(kept, combine)) {
        return '';
    }
    const before = JSON.stringify(root);
    const after = JSON.stringify(kept);
    return before === after ? conditions : after;
}

function hasLeaf(node: Fields, combine: string): boolean {
    if (node['type'] !== combine) {
        return true;
    }
    const children = Array.isArray(node['conditions']) ? node['conditions'] : [];
    return children.some((child: unknown) => isFields(child) && hasLeaf(child, combine));
}

/** The type of the condition tree's groups, as the builder writes it. */
export const COMBINE_TYPE = 'rich_snippets/combine';
