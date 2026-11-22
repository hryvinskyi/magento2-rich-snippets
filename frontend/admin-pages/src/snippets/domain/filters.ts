/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the chips above the list show and which rows each lets through: every snippet; the ones that need attention
 * (a bad or a warning flag); the snippets set for one kind of page; the building blocks. The counts are the server's
 * for the store view picked; the "needs attention" chip is drawn only while something needs it. And what the card
 * says when a chip lets nothing through. Pure: the words are chosen as phrases or are the server's.
 */
import { phrase, type Phrase } from '@hryvinskyi/admin-ui';
import type { PageKind, SnippetCounts, SnippetRow } from './model.ts';

interface Filters {
    all: Record<never, never>;
    attention: Record<never, never>;
    blocks: Record<never, never>;
    page: { readonly page: string };
}

export type FilterKind = keyof Filters;

/** What the list shows: every snippet, the ones that need attention, one kind of page's, or the building blocks. */
export type Filter<K extends FilterKind = FilterKind> = { [P in K]: { readonly kind: P } & Filters[P] }[K];

export const ALL: Filter<'all'> = Object.freeze({ kind: 'all' });

/** One chip: the filter it applies, its words (a phrase of the page's, or a kind of page's label) and its count. */
export interface Chip {
    readonly key: string;
    readonly filter: Filter;
    readonly label: Phrase | string;
    readonly count: number;
    /** `warning` draws the chip in the warning tint, to draw the eye to rows that need something. */
    readonly tone: 'neutral' | 'warning';
}

/** Whether a row carries a flag that keeps its pages from a result or is worth a look. */
export const needsAttention = (row: SnippetRow): boolean => row.flags.bad !== null || row.flags.warn !== null;

const MATCHES: { readonly [K in FilterKind]: (filter: Filter<K>, row: SnippetRow) => boolean } = {
    all: () => true,
    attention: (_filter, row) => needsAttention(row),
    blocks: (_filter, row) => row.isBlock,
    page: (filter, row) => !row.isBlock && row.pageKinds.includes(filter.page),
};

function matches<K extends FilterKind>(filter: Filter<K>, row: SnippetRow): boolean {
    return MATCHES[filter.kind](filter, row);
}

/** The rows a filter lets through, in the list's order. */
export const rowsOf = (rows: readonly SnippetRow[], filter: Filter): readonly SnippetRow[] =>
    rows.filter((row) => matches(filter, row));

/** A key naming a filter, the same for two filters that let the same rows through. */
export const filterKey = (filter: Filter): string => (filter.kind === 'page' ? `page:${filter.page}` : filter.kind);

/** The chips in the design's order: every snippet, needs attention (only while something does), each kind, blocks. */
export function chipsOf(counts: SnippetCounts, kinds: readonly PageKind[]): readonly Chip[] {
    const chip = (filter: Filter, label: Phrase | string, count: number, tone: Chip['tone'] = 'neutral'): Chip =>
        ({ key: filterKey(filter), filter, label, count, tone });
    return [
        chip(ALL, phrase('snippets.chips.all'), counts.all),
        ...(counts.attention > 0 ? [chip({ kind: 'attention' }, phrase('snippets.chips.attention'), counts.attention, 'warning')] : []),
        ...kinds.map((kind) => chip({ kind: 'page', page: kind.code }, kind.label, counts.kinds.get(kind.code) ?? 0)),
        chip({ kind: 'blocks' }, phrase('snippets.chips.blocks'), counts.blocks),
    ];
}

const NO_MATCH: {
    readonly [K in FilterKind]: (filter: Filter<K>, kinds: ReadonlyMap<string, PageKind>, store: string | null) => Phrase
} = {
    all: (_filter, _kinds, store) => phrase('snippets.noMatch.store', store ?? ''),
    attention: () => phrase('snippets.noMatch.attention'),
    blocks: () => phrase('snippets.noMatch.blocks'),
    page: (filter, kinds, store) => {
        const words = kinds.get(filter.page)?.phrase ?? filter.page;
        return store === null ? phrase('snippets.noMatch.kind', words) : phrase('snippets.noMatch.kindIn', words, store);
    },
};

/**
 * What the card says when the filter lets nothing through: no snippet needs attention, none is set for the kind of
 * page (in the store view picked), there is no building block, or — every snippet, in one store view — nothing is
 * output there.
 */
export function noMatchOf<K extends FilterKind>(
    filter: Filter<K>,
    kinds: ReadonlyMap<string, PageKind>,
    store: string | null,
): Phrase {
    return NO_MATCH[filter.kind](filter, kinds, store);
}
