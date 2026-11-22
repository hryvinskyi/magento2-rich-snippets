/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the row asks before a delete, and what the page says when a write is refused. Pure: phrases chosen from what
 * the server says.
 *
 * Deleting asks first, in the row: a snippet that is on stops being written from the next page view, one that is off
 * changes nothing on the storefront. A building block that snippets use cannot be deleted, and the row says which
 * snippets use it instead — from the list, or from the server's refusal when the list was out of date.
 */
import { phrase, type Phrase } from '@hryvinskyi/admin-ui';
import type { SnippetReference, SnippetRow } from './model.ts';
import { referenceName } from './row-view.ts';

interface DeleteConfirms {
    /** Whether to delete it, and what that changes on the storefront. */
    ask: { readonly question: Phrase };
    /** That it cannot be deleted while these snippets use it. */
    inUse: { readonly users: readonly string[] };
}

export type DeleteConfirmKind = keyof DeleteConfirms;

/** The question under a row before it is deleted. */
export type DeleteConfirm<K extends DeleteConfirmKind = DeleteConfirmKind> =
    { [P in K]: { readonly kind: P } & DeleteConfirms[P] }[K];

/**
 * The question for a row: whether to delete it, or — for a snippet others use — that it cannot be.
 *
 * @param includers the snippets the server says use it; null to read them from the row
 */
export function deleteConfirmOf(row: SnippetRow, includers: readonly SnippetReference[] | null): DeleteConfirm {
    const users = (includers ?? row.usedIn).map(referenceName);
    if (users.length > 0) {
        return { kind: 'inUse', users };
    }
    return {
        kind: 'ask',
        question: phrase(!row.isBlock && row.active ? 'snippets.delete.askOn' : 'snippets.delete.askOff', row.name),
    };
}

/** The reasons a write on one snippet is refused for that the page has words of their own for. */
const REFUSALS: ReadonlyMap<string, string> = new Map([
    ['block_cannot_be_switched_on', 'snippets.refused.block_cannot_be_switched_on'],
    ['stores_missing', 'snippets.refused.stores_missing'],
    ['template_invalid', 'snippets.refused.template_invalid'],
    ['unknown_snippet', 'snippets.refused.unknown_snippet'],
]);

/** What the page says about a refused write: the reason's own words, or the general ones. */
export const refusalOf = (reason: string): Phrase => phrase(REFUSALS.get(reason) ?? 'snippets.refused.other');
