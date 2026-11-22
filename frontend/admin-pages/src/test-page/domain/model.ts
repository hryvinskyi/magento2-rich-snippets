/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's model, as the server answers it and the page reads it: a page picked to test and what the test
 * found on it, and where the catalog check stands with the findings of the last one. Every sentence the server words
 * arrives worded; the page adds only its fixed words around them.
 */

/** The two questions the page answers: one page, or the whole catalog. */
export const MODES = ['page', 'catalog'] as const;
export type Mode = (typeof MODES)[number];

/**
 * A page to test, as the picker shows it: `ref` as the page picker names it (`product:12`), null for a storefront
 * path; `path` the path on the storefront, empty until a test reads it when the page came from a list that does not
 * carry it.
 */
export interface PickedPage {
    readonly ref: string | null;
    readonly kind: string;
    readonly label: string;
    readonly path: string;
}

/** The pages the picker finds for a text, and the text itself when it is a storefront path. */
export interface SearchAnswer {
    readonly pages: readonly PickedPage[];
    readonly address: string | null;
}

export const BLOCK_VERDICTS = ['ok', 'bad'] as const;
export type BlockVerdict = (typeof BLOCK_VERDICTS)[number];

/** One script block of the page: the snippet that wrote it (none when Rich Snippets did not), its JSON and verdict. */
export interface TestedBlock {
    readonly code: string | null;
    readonly snippetId: number | null;
    readonly name: string | null;
    readonly type: string;
    readonly json: string;
    /** Bytes, as the page carries it. */
    readonly size: number;
    readonly lines: number;
    readonly verdict: BlockVerdict;
    readonly summary: string;
}

/** Why a snippet is not output on the page: the reason's code and the server's words for it. */
export interface NotOutput {
    readonly code: string;
    readonly snippetId: number | null;
    readonly name: string;
    readonly reason: string;
    readonly text: string;
}

export const FEATURE_VERDICTS = ['met', 'not_met', 'left_out'] as const;
export type FeatureVerdict = (typeof FEATURE_VERDICTS)[number];

/** One kind of Google result the page's blocks can have, and how the page stands against its rules. */
export interface Feature {
    readonly rule: string;
    readonly label: string;
    readonly verdict: FeatureVerdict;
    readonly description: string;
    readonly missingRequired: readonly string[];
    readonly missingRecommended: readonly string[];
}

/** Why the storefront could not be read, worded by the server. */
export interface ReadFailure {
    readonly reason: string;
    readonly httpStatus: number | null;
    readonly text: string;
}

/** What a page test found. A page that could not be read has a failure and nothing else. */
export interface PageTest {
    readonly page: PickedPage;
    readonly store: number;
    readonly durationMs: number;
    readonly failure: ReadFailure | null;
    readonly blocks: readonly TestedBlock[];
    /** The theme's own markup on the page, worded; null when there is none. */
    readonly themeMarkup: string | null;
    readonly notOutput: readonly NotOutput[];
    readonly features: readonly Feature[];
}

export const CHECK_STATES = ['idle', 'requested', 'running'] as const;
export type CheckState = (typeof CHECK_STATES)[number];

export const STATUS_TONES = ['invalid', 'ok', 'busy', 'none'] as const;
export type StatusTone = (typeof STATUS_TONES)[number];

export interface Fact {
    readonly label: string;
    readonly value: number;
}

export interface Progress {
    readonly checked: number;
    readonly total: number;
    readonly percent: number;
    readonly text: string;
    /** How long is left; null until it can be told. */
    readonly left: string | null;
}

/** The last finished run: its id, and what the page says once it is finished. */
export interface LastRun {
    readonly id: number;
    readonly toast: string;
}

/** Where the catalog check stands, worded by the server. */
export interface CheckStatus {
    readonly state: CheckState;
    readonly tone: StatusTone;
    readonly title: string;
    readonly note: string;
    readonly facts: readonly Fact[];
    readonly progress: Progress | null;
    readonly last: LastRun | null;
    /** The newest run failed after the last finished one, worded; null otherwise. */
    readonly failed: string | null;
}

export const FINDING_TONES = ['bad', 'warn', 'note'] as const;
export type FindingTone = (typeof FINDING_TONES)[number];

export interface FindingSnippet {
    readonly code: string;
    readonly name: string;
}

/** A gap the last check found, with the server's words for the table, its drawer and its download. */
export interface Finding {
    readonly key: string;
    readonly tone: FindingTone;
    readonly title: string;
    readonly description: string;
    readonly fix: string;
    readonly snippets: readonly FindingSnippet[];
    readonly pages: number;
    readonly allProducts: boolean;
    readonly meta: string;
    readonly label: string;
    readonly saved: string;
}

/** The findings of the last check, of one store view or of every one (`store` null). */
export interface FindingsTable {
    readonly store: number | null;
    readonly subtitle: string;
    readonly findings: readonly Finding[];
}

/** One page behind a finding. */
export interface FindingItem {
    readonly ref: string;
    readonly kind: string;
    readonly id: number | null;
    readonly store: number;
    readonly label: string;
    readonly sku: string | null;
    readonly group: string | null;
}

/** One part of the pages behind a finding. */
export interface FindingItems {
    readonly key: string;
    readonly total: number;
    readonly page: number;
    readonly pageSize: number;
    /** Which part of the list is shown, when it is not all of it. */
    readonly note: string | null;
    readonly items: readonly FindingItem[];
}

/** What the page is printed with. */
export interface TestData {
    readonly mode: Mode;
    /** The store view the page test reads. */
    readonly store: number;
    /** The page tested first; null when there is none. */
    readonly page: PickedPage | null;
    /** The store view the findings are of; null for every one. */
    readonly catalogStore: number | null;
    /** The finding whose pages open first; null for none. */
    readonly finding: string | null;
    /** When the check runs by itself, worded. */
    readonly schedule: string;
    readonly status: CheckStatus;
    readonly findings: FindingsTable;
}
