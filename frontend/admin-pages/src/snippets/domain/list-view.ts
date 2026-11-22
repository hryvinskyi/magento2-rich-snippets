/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Snippets card holds under its head, and which of the page's parts show. Pure.
 *
 * - While the list of the store view picked loads, grey rows; after it failed, the failure; with no snippet at all in
 *   the shop, the first-visit invitation; when the chip lets nothing through, what is not there; else the rows.
 * - The chips show while a list with snippets is shown; the store-view bar while the shop has snippets, or a store
 *   view is picked (so the admin can go back); *Export all* while a list with snippets is shown, *Import* while a list
 *   is shown.
 */
import type { Phrase } from '@hryvinskyi/admin-ui';
import { noMatchOf, rowsOf, type Filter } from './filters.ts';
import type { Listing, PageKind, SnippetRow } from './model.ts';

/** Where the list on screen stands. */
export type ListStatus = 'ready' | 'loading' | 'failed';

interface CardBodies {
    loading: Record<never, never>;
    failed: Record<never, never>;
    empty: Record<never, never>;
    noMatch: { readonly text: Phrase };
    rows: { readonly rows: readonly SnippetRow[] };
}

export type CardBodyKind = keyof CardBodies;

export type CardBody<K extends CardBodyKind = CardBodyKind> = { [P in K]: { readonly kind: P } & CardBodies[P] }[K];

/**
 * The card's body.
 *
 * @param store the name of the store view picked; null for every store view
 */
export function cardBodyOf(
    status: ListStatus,
    listing: Listing,
    filter: Filter,
    store: string | null,
    kinds: ReadonlyMap<string, PageKind>,
): CardBody {
    if (status !== 'ready') {
        return { kind: status };
    }
    if (listing.rows.length === 0 && store === null) {
        return { kind: 'empty' };
    }
    const rows = rowsOf(listing.rows, filter);
    return rows.length === 0 ? { kind: 'noMatch', text: noMatchOf(filter, kinds, store) } : { kind: 'rows', rows };
}

/** Which of the page's parts show for the list on screen. */
export interface Parts {
    readonly chips: boolean;
    readonly storeBar: boolean;
    readonly exportAll: boolean;
    readonly importFile: boolean;
}

export function partsOf(status: ListStatus, listing: Listing, store: number | null): Parts {
    const ready = status === 'ready';
    const hasRows = listing.rows.length > 0;
    return {
        chips: ready && hasRows,
        storeBar: hasRows || store !== null,
        exportAll: ready && hasRows,
        importFile: ready,
    };
}
