/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What one row of the list says and how it looks, and the line under the card's title. Pure: the sentences are put
 * together through the page's words and dates, from what the server says about the snippet.
 *
 * - The "where" line of a page snippet: its kinds of page as a sentence names them, joined the locale's way and
 *   starting upper case, then its conditions in words, then the store views it is for: "Product pages, where Product
 *   type is not Configurable · All store views". A snippet set for no store view the shop still has says so, never
 *   "All store views". A building block has no page of its own and names the snippets it is written inside.
 * - The meta line: how many properties, which snippets it uses, when and by whom it was changed.
 * - The look: the glyph (a building block's, else the first kind of page's), the edge (red for a bad flag, amber for
 *   a warning), and whether the row is drawn muted (a page snippet that is off).
 */
import type { PageKind, SnippetCounts, SnippetReference, SnippetRow } from './model.ts';
import { capitalised, joinParts, type Days, type Words } from './wording.ts';

const BLOCK_ICON = 'widgets';
const EVERY_STORE = 0;
const FALLBACK_ICON = 'web';

/** A reference by its name, or by its code when no snippet of the shop has the code. */
export const referenceName = (reference: SnippetReference): string => reference.name ?? reference.code;

/** The kinds of page as a sentence names them, joined the locale's way: "product pages and category pages". */
export const kindsPhrase = (codes: readonly string[], kinds: ReadonlyMap<string, PageKind>, words: Words): string =>
    words.list(codes.map((code) => kinds.get(code)?.phrase ?? code));

/** Where a snippet is output, in one line. */
export function placementLine(row: SnippetRow, kinds: ReadonlyMap<string, PageKind>, words: Words): string {
    if (row.isBlock) {
        return row.usedIn.length === 0
            ? words.t('snippets.blocks.unused')
            : words.t('snippets.blocks.inside', words.list(row.usedIn.map(referenceName)));
    }
    const views = row.storeIds.includes(EVERY_STORE)
        ? words.t('snippets.row.allViews')
        : row.storeNames.length === 0
            ? words.t('snippets.row.noViews')
            : words.t('snippets.row.someViews', words.list(row.storeNames));
    if (row.pageKinds.length === 0) {
        return joinParts([words.t('snippets.row.noKinds'), views]);
    }
    const pages = capitalised(kindsPhrase(row.pageKinds, kinds, words));
    const where = row.conditionSummary === '' ? pages : words.t('snippets.row.where', pages, row.conditionSummary);
    return joinParts([where, views]);
}

/** How many properties, what it uses, and when and by whom it was changed, in one line. */
export function metaLine(row: SnippetRow, words: Words, days: Days): string {
    const changed = row.updatedAt === null
        ? ''
        : row.updatedBy === ''
            ? words.t('snippets.row.changedAt', days.short(row.updatedAt))
            : words.t('snippets.row.changed', days.short(row.updatedAt), row.updatedBy);
    return joinParts([
        words.tn('snippets.row.properties', row.propertyCount),
        row.uses.length === 0 ? '' : words.t('snippets.row.uses', words.list(row.uses.map(referenceName))),
        changed,
    ]);
}

/** The red or amber edge of a row, or none. */
export type RowEdge = 'bad' | 'warn' | 'none';

export interface RowLook {
    readonly icon: string;
    readonly edge: RowEdge;
    /** A page snippet that is off: its name and its row are drawn muted. */
    readonly muted: boolean;
}

/** How a row looks, with the switch as it is shown (the state wanted while a switch is on its way). */
export function rowLook(row: SnippetRow, active: boolean, kinds: ReadonlyMap<string, PageKind>): RowLook {
    const first = row.pageKinds[0];
    const kindIcon = first === undefined ? FALLBACK_ICON : (kinds.get(first)?.icon ?? FALLBACK_ICON);
    return {
        icon: row.isBlock ? BLOCK_ICON : kindIcon,
        edge: row.flags.bad !== null ? 'bad' : row.flags.warn !== null ? 'warn' : 'none',
        muted: !row.isBlock && !active,
    };
}

/** The line under the card's title: how many are on and off, and how many building blocks; or that nothing is. */
export function subtitleOf(counts: SnippetCounts, words: Words): string {
    if (counts.all === 0) {
        return words.t('snippets.card.empty');
    }
    const blocks = words.tn('snippets.card.blocks', counts.blocks);
    return counts.off === 0
        ? words.t('snippets.card.subtitle', words.n(counts.on), blocks)
        : words.t('snippets.card.subtitleOff', words.n(counts.on), words.n(counts.off), blocks);
}
