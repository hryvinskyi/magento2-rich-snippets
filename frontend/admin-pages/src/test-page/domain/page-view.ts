/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the "One page" mode decides before it words anything: how a page is named in the picker, which line the bar
 * shows, what the output card's subtitle counts, how each block and each Google result looks, which sentence says
 * that nothing is output, and where its links lead.
 *
 * - A page is named in the picker by its kind (`Product · Field satchel`); a kind with no name of its own (the home
 *   page) by its label alone. The pages a search finds come first, the text as any address after them.
 * - Sizes are in kilobytes with one decimal; a test's time in seconds with one decimal, never below a tenth.
 * - A block with a problem draws the error glyph; a result met draws a filled check, one not met a filled error, one
 *   left out a muted minus.
 */
import { linkTo, phrase, withParam, type Phrase, type ScreenLink } from '@hryvinskyi/admin-ui';
import type { BlockVerdict, FeatureVerdict, Mode, PickedPage, SearchAnswer, TestedBlock } from './model.ts';

export const EDITOR = 'hryvinskyi_richsnippets/snippets/edit';
export const SETTINGS = 'adminhtml/system_config/edit';
const SETTINGS_SECTION = 'hryvinskyi_rich_snippets';

/** The editor's section a link to the conditions opens. */
const CONDITIONS_SECTION = 'pages';

/** The kind a storefront path is tested as. */
export const ANY_ADDRESS = 'other';

/** The phrase naming a page of each kind in the picker; `%1` is the page's label. */
const PICKER_NAMES: Readonly<Record<string, string>> = Object.freeze({
    product: 'test.picker.kind.product',
    category: 'test.picker.kind.category',
    cms: 'test.picker.kind.cms',
    [ANY_ADDRESS]: 'test.picker.kind.other',
});

/** How the picker names a page; null when its kind has no name, and its label alone names it. */
export function pickerName(page: PickedPage): Phrase | null {
    const key = PICKER_NAMES[page.kind];
    return key === undefined ? null : phrase(key, page.label);
}

/** Where a page is on the storefront, as the bar and the output card say it: its path, else its label. */
export const whereOf = (page: PickedPage): string => (page.path === '' ? page.label : page.path);

/** Whether two picks are the same page. */
export const samePage = (a: PickedPage, b: PickedPage): boolean =>
    a.ref === null && b.ref === null ? a.path === b.path : a.ref === b.ref;

/** The picker's options for a search answer: the pages found, then the text as any address. */
export function searchOptions(answer: SearchAnswer): readonly PickedPage[] {
    return answer.address === null
        ? answer.pages
        : [...answer.pages, { ref: null, kind: ANY_ADDRESS, label: answer.address, path: answer.address }];
}

/** What the bar says about the last test. */
export type RunLine = 'none' | 'busy' | 'done' | 'failed' | 'error';

/** The bar's line: what is read now, how long the last test took, or why it gave nothing. */
export function runLine(line: RunLine, where: string, durationMs: number): Phrase {
    const lines: { readonly [L in RunLine]: () => Phrase } = {
        none: () => phrase('test.bar.line.none'),
        busy: () => phrase('test.bar.line.busy', where),
        done: () => phrase('test.bar.line.done', seconds(durationMs)),
        failed: () => phrase('test.bar.line.failed'),
        error: () => phrase('test.bar.line.error'),
    };
    return lines[line]();
}

/** A test's time in seconds, one decimal, never below a tenth. */
export const seconds = (durationMs: number): number => Math.max(1, Math.round(durationMs / 100)) / 10;

/** A size in kilobytes, one decimal. */
export const kilobytes = (bytes: number): number => Math.round(bytes / 102.4) / 10;

/** What the output card's subtitle counts: the blocks, their size together, and those with a problem. */
export interface OutputSummary {
    readonly count: number;
    readonly kilobytes: number;
    readonly problems: number;
}

export function outputSummary(blocks: readonly TestedBlock[]): OutputSummary {
    return {
        count: blocks.length,
        kilobytes: kilobytes(blocks.reduce((total, block) => total + block.size, 0)),
        problems: blocks.filter((block) => block.verdict === 'bad').length,
    };
}

export interface BlockLook {
    readonly icon: string;
    readonly bad: boolean;
}

export const BLOCK_LOOKS = Object.freeze({
    ok: { icon: 'check_circle', bad: false },
    bad: { icon: 'error', bad: true },
} as const satisfies Record<BlockVerdict, BlockLook>);

export interface FeatureLook {
    readonly word: string;
    readonly tone: 'ok' | 'error' | 'muted';
    readonly icon: string;
}

export const FEATURE_LOOKS = Object.freeze({
    met: { word: 'test.req.met', tone: 'ok', icon: 'check_circle' },
    not_met: { word: 'test.req.notMet', tone: 'error', icon: 'error' },
    left_out: { word: 'test.req.leftOut', tone: 'muted', icon: 'remove_circle' },
} as const satisfies Record<FeatureVerdict, FeatureLook>);

/** The sentence under "Nothing is output on this page": for an address, a page, or a shop with no snippet for it. */
export function nothingText(kind: string, notOutput: number): Phrase {
    if (notOutput === 0) {
        return phrase('test.nothing.none');
    }
    return phrase(kind === ANY_ADDRESS ? 'test.nothing.address' : 'test.nothing.page');
}

/** The editor of a snippet. */
export const editorLink = (snippetId: number): ScreenLink => withParam(linkTo(EDITOR), 'id', String(snippetId));

/** The editor of a snippet, opened on the section that holds its conditions. */
export const conditionsLink = (snippetId: number): ScreenLink =>
    withParam(editorLink(snippetId), 'section', CONDITIONS_SECTION);

/** The module's settings, where the theme's own markup is removed. */
export const settingsLink = (): ScreenLink => withParam(linkTo(SETTINGS), 'section', SETTINGS_SECTION);

/**
 * The parameters of the page's address for what it shows, so a reload opens the same: the catalog with the store view
 * of its findings, or the page tested with its store view.
 */
export function addressParams(
    mode: Mode,
    page: PickedPage | null,
    store: number,
    catalogStore: number | null,
): ReadonlyMap<string, string> {
    if (mode === 'catalog') {
        return new Map([['mode', 'catalog'], ...(catalogStore === null ? [] : [['store', String(catalogStore)] as const])]);
    }
    const named: readonly (readonly [string, string])[] = page === null
        ? []
        : [page.ref === null ? ['path', page.path] : ['page', page.ref]];
    return new Map([...named, ['store', String(store)]]);
}
