/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview as the page reads it, for every store view or one. Every word in it is the server's, translated; the
 * page adds only its own fixed wording around it. Counts and verdicts are the server's too: the page shows them and
 * decides nothing about them.
 */
import type { ScreenLink } from '@hryvinskyi/admin-ui';

/** How far the shop has set up its snippets: nothing added, added and all off, or something output. */
export type SetupState = 'none' | 'off' | 'live';
export const SETUP_STATES: readonly SetupState[] = Object.freeze(['none', 'off', 'live']);

/** The status card's look: live with nothing invalid, live with invalid pages, all off, nothing added. */
export type StatusTone = 'ok' | 'invalid' | 'off' | 'none';
export const STATUS_TONES: readonly StatusTone[] = Object.freeze(['ok', 'invalid', 'off', 'none']);

/** How much of the shop has what a kind of Google result needs. */
export type FeatureStatus = 'met' | 'partly' | 'no_result';
export const FEATURE_STATUSES: readonly FeatureStatus[] = Object.freeze(['met', 'partly', 'no_result']);

/** How much an item of the attention list matters: an invalid page, a recommendation, a note. */
export type AttentionTone = 'bad' | 'warn' | 'info';
export const ATTENTION_TONES: readonly AttentionTone[] = Object.freeze(['bad', 'warn', 'info']);

/** A link with its words. */
export interface LabelledLink {
    readonly label: string;
    readonly link: ScreenLink;
}

/** The header badge: whether anything is output, and what. */
export interface Badge {
    readonly on: boolean;
    readonly text: string;
}

export interface Status {
    readonly tone: StatusTone;
    readonly word: string;
    readonly note: string;
    readonly action: LabelledLink | null;
}

export interface Fact {
    readonly key: string;
    readonly label: string;
    readonly value: string;
    /** Drawn in the warning colour: something the merchant should look at. */
    readonly warn: boolean;
}

/** A snippet a kind of page carries. */
export interface KindSnippet {
    readonly id: number;
    readonly code: string;
    readonly name: string;
    readonly on: boolean;
    /** A short note beside the name (how many pages, `off`); null for none. */
    readonly sub: string | null;
}

export interface KindRow {
    readonly kind: string;
    readonly label: string;
    /** A glyph name. */
    readonly icon: string;
    /** How many pages, or which pages. */
    readonly pages: string;
    /** A page of this kind to test; null when there is none to show. */
    readonly see: ScreenLink | null;
    /** What the row says when it carries no snippet; null while it carries some. */
    readonly empty: string | null;
    readonly snippets: readonly KindSnippet[];
}

export interface FeatureRow {
    readonly rule: string;
    readonly label: string;
    /** Which pages the result is for. */
    readonly on: string;
    readonly met: number;
    readonly total: number;
    /** Whether a bar shows how far `met` is of `total`. */
    readonly bar: boolean;
    readonly text: string;
    readonly status: FeatureStatus;
    readonly statusLabel: string;
}

/** What a card shows in place of rows it does not have. */
export interface EmptyNote {
    readonly title: string;
    readonly text: string;
}

export interface Features {
    readonly subtitle: string;
    readonly rows: readonly FeatureRow[];
    /** Where the numbers come from, while there are rows; null otherwise. */
    readonly footer: string | null;
    /** In place of the rows; null while there are rows. */
    readonly empty: EmptyNote | null;
}

export interface AttentionItem {
    readonly key: string;
    readonly tone: AttentionTone;
    readonly title: string;
    readonly description: string;
    /** The words of the link to the screen that fixes it. */
    readonly action: string;
    readonly link: ScreenLink;
}

export interface Attention {
    readonly count: number;
    readonly items: readonly AttentionItem[];
    /** What the list says when nothing wants fixing; null while something does. */
    readonly none: string | null;
}

export interface OverviewData {
    /** The store view the numbers are for; null for every store view together. */
    readonly store: number | null;
    readonly state: SetupState;
    readonly badge: Badge;
    readonly status: Status;
    readonly facts: readonly Fact[];
    readonly kinds: { readonly subtitle: string; readonly rows: readonly KindRow[] };
    readonly features: Features;
    readonly attention: Attention;
}
