/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet list as the page reads it, and what importing a file would do. Every word in it is the server's,
 * translated — a snippet's name, a condition in words, a flag, a store view's name, a kind of page's label; the page
 * adds only its own fixed wording around it. Counts, flags and refusals are the server's too: the page shows them and
 * decides nothing about them.
 */

/** A snippet another snippet uses or is used by; `name` is null for a code no snippet of the shop has. */
export interface SnippetReference {
    readonly code: string;
    readonly name: string | null;
}

/** The first message of each tone a snippet carries; null for a tone it has none of. */
export interface SnippetFlags {
    /** Something that keeps the pages from a Google result ("No offer: Google needs one"). */
    readonly bad: string | null;
    /** Something worth a look ("Second Product on the same pages"). */
    readonly warn: string | null;
    /** A plain remark ("EAN is empty for 23 products"). */
    readonly note: string | null;
}

export interface SnippetRow {
    readonly id: number;
    readonly code: string;
    readonly name: string;
    /** The schema.org type of the template's root; empty when it has none. */
    readonly type: string;
    /** A building block is written only inside the snippets that use it: it has no pages and no switch. */
    readonly isBlock: boolean;
    readonly active: boolean;
    /** The codes of the kinds of page it is set for, in their configured order. */
    readonly pageKinds: readonly string[];
    /** Its conditions in words ("Product type is not Configurable"); empty when it has none. */
    readonly conditionSummary: string;
    /** The store views it is set for, by ID: `[0]` is every store view, none when every one it was set for is gone. */
    readonly storeIds: readonly number[];
    /** The names of the store views it is limited to that the shop still has; empty for every store view or none. */
    readonly storeNames: readonly string[];
    readonly propertyCount: number;
    readonly uses: readonly SnippetReference[];
    readonly usedIn: readonly SnippetReference[];
    /** When it was saved last (`Y-m-d H:i:s`, UTC); null when that is not known. */
    readonly updatedAt: string | null;
    /** Who saved it last; empty when that is not known. */
    readonly updatedBy: string;
    readonly flags: SnippetFlags;
}

/** What the list counts, for the store view it is of. */
export interface SnippetCounts {
    readonly all: number;
    readonly attention: number;
    /** How many snippets are set for each kind of page, by kind code. */
    readonly kinds: ReadonlyMap<string, number>;
    readonly blocks: number;
    readonly on: number;
    readonly off: number;
}

export interface Listing {
    readonly rows: readonly SnippetRow[];
    readonly counts: SnippetCounts;
}

/** A kind of page as the page shows it: a label for its chip, its name inside a sentence, and its glyph. */
export interface PageKind {
    readonly code: string;
    /** "Product pages". */
    readonly label: string;
    /** "product pages", as a sentence names it. */
    readonly phrase: string;
    readonly icon: string;
}

/** What the page is printed with. */
export interface SnippetsData {
    readonly kinds: readonly PageKind[];
    /** Where a snippet that is on writes its script block, as the store views' settings say. */
    readonly outputLine: string;
    readonly list: Listing;
}

/** Why a write on one snippet was refused; `includers` names the snippets that use one that may not be deleted. */
export interface Refusal {
    readonly reason: string;
    readonly includers: readonly SnippetReference[];
}

/** The answer to a write on one snippet: the list after it, the snippet written, and the refusal if there was one. */
export interface WriteAnswer {
    readonly list: Listing;
    /** The snippet written (the copy, for a duplicate); null for a delete and after a refusal. */
    readonly id: number | null;
    readonly refused: Refusal | null;
}

/** How a snippet of an import file stands against the shop's snippet with its code. */
export type ImportStatus = 'new' | 'changed' | 'identical' | 'invalid';
export const IMPORT_STATUSES: readonly ImportStatus[] = Object.freeze(['new', 'changed', 'identical', 'invalid']);

/** What can be done with a snippet of an import file. */
export type ImportChoice = 'replace' | 'keep_both' | 'add' | 'skip';
export const IMPORT_CHOICES: readonly ImportChoice[] = Object.freeze(['replace', 'keep_both', 'add', 'skip']);

/** Something about a snippet of an import file this shop cannot take as it is, with the values the reason names. */
export interface ImportProblem {
    readonly reason: string;
    readonly params: ReadonlyMap<string, string>;
}

export interface ImportEntry {
    readonly code: string;
    readonly name: string;
    readonly type: string;
    readonly isBlock: boolean;
    readonly status: ImportStatus;
    /** The shop's snippet with the code; null when the shop has none. */
    readonly existingId: number | null;
    /** When the shop's snippet was saved last (`Y-m-d H:i:s`, UTC); null when there is none. */
    readonly changedHere: string | null;
    /** When the snippet in the file was saved last, as the file says; null when it does not say. */
    readonly changedInFile: string | null;
    /** What may be done with it, the one offered by default first; empty when there is nothing to do. */
    readonly choices: readonly ImportChoice[];
    readonly problems: readonly ImportProblem[];
    readonly pageKinds: readonly string[];
    readonly conditionSummary: string;
}

/** What an import file holds and what importing it would do, before anything is changed. */
export interface ImportPreview {
    /** When the file was exported, as it says; empty when it does not say. */
    readonly exportedAt: string;
    /** The shop it was exported from, as it says; empty when it does not say. */
    readonly source: string;
    readonly snippets: readonly ImportEntry[];
}

/** The answer to an import: the list after it, and how many snippets were added or replaced. */
export interface ImportAnswer {
    readonly list: Listing;
    readonly imported: number;
}

/** An export saved by the browser: the file's name, and how many snippets it holds. */
export interface ExportedFile {
    readonly fileName: string;
    readonly count: number;
}
