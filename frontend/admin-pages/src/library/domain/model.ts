/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The library as the page reads it, the questions a template asks, and what an add ended with. Every word in it is
 * the server's, translated — a template's name, its description, where it is output, what Google does with it, a
 * group's label, an answer and its hint; the page adds only its own fixed wording around it. Whether a template was
 * added, the name of the next copy and the answer suggested in advance are the server's too: the page shows them and
 * decides nothing about them.
 */

/** What Google does with a template's type today. */
export type GoogleStatus = 'ok' | 'none' | 'gone';
export const GOOGLE_STATUSES: readonly GoogleStatus[] = Object.freeze(['ok', 'none', 'gone']);

/** A group the library lists its templates in, in the library's order. */
export interface LibraryGroup {
    readonly code: string;
    readonly label: string;
}

/** A building block a template includes, and whether the shop has one already (then the copy uses it). */
export interface TemplateUse {
    readonly code: string;
    readonly name: string;
    readonly inShop: boolean;
}

/** A question a template asks when it is added, as the drawer heads it while its answers load. */
export interface TemplateQuestion {
    readonly code: string;
    readonly label: string;
}

export interface LibraryTemplate {
    readonly code: string;
    readonly version: number;
    readonly group: string;
    readonly name: string;
    /** The schema.org type of the template's root. */
    readonly type: string;
    readonly icon: string;
    readonly description: string;
    /** What it covers in a few words, for the recommended set; empty when it has none. */
    readonly summary: string;
    /** Where the copy is output, in words. */
    readonly where: string;
    /** A building block is written only inside the snippets that use it. */
    readonly isBlock: boolean;
    readonly google: {
        readonly status: GoogleStatus;
        /** The line on the card. */
        readonly short: string;
        /** The text in the add drawer. */
        readonly long: string;
    };
    /** The property names it writes. */
    readonly properties: readonly string[];
    readonly uses: readonly TemplateUse[];
    readonly questions: readonly TemplateQuestion[];
    /** Whether the shop has a snippet added from it. */
    readonly added: boolean;
    /** Whether a copy was added from an older version of it. */
    readonly hasNewerVersion: boolean;
    /** The name the next copy gets ("Product 2" when the shop has a "Product"). */
    readonly nextName: string;
}

/** The library with what the shop has of it. */
export interface Library {
    readonly groups: readonly LibraryGroup[];
    /** The codes of the recommended templates, in the order they are added. */
    readonly recommended: readonly string[];
    readonly templates: readonly LibraryTemplate[];
}

/** What the page is printed with: the library, and the template whose add drawer opens first, if any. */
export interface LibraryData {
    readonly library: Library;
    readonly open: string | null;
    /** The server could not read the library when it printed the page; the library then holds no template. */
    readonly unavailable: boolean;
}

/** How the hint under an answer reads. */
export type HintTone = 'ok' | 'warn';
export const HINT_TONES: readonly HintTone[] = Object.freeze(['ok', 'warn']);

/** One answer to a question: its key, its words in the select, the hint under it once chosen. */
export interface AnswerOption {
    readonly key: string;
    readonly label: string;
    /** Whether choosing it leaves the property out. */
    readonly leftOut: boolean;
    readonly hint: string;
    readonly tone: HintTone;
    /** How many product pages fill the attribute it reads; null for an answer that reads none. */
    readonly fillCount: number | null;
}

/** The answers a question offers in this shop, with the one suggested in advance. */
export interface QuestionAnswers {
    readonly question: string;
    readonly label: string;
    /** The key of the suggested answer, one of the options'. */
    readonly default: string;
    /** How many product pages the fill counts are out of; null when no answer reads an attribute. */
    readonly total: number | null;
    readonly options: readonly AnswerOption[];
}

/** The questions of one template, in the order they are asked. */
export interface TemplateAnswers {
    readonly template: string;
    readonly questions: readonly QuestionAnswers[];
}

/** A snippet an add ended with: one it created, switched off, or a building block it reused. */
export interface AddedSnippet {
    readonly id: number;
    readonly code: string;
    readonly name: string;
    readonly isBlock: boolean;
    /** The library code of the template it stands for. */
    readonly template: string;
    readonly reused: boolean;
}

/** Why an add was refused: a snippet rule's reason, or `template_invalid`. */
export interface AddRefusal {
    readonly reason: string;
}

/**
 * The answer to an add: what it ended with (each template's building blocks first, then the copy), the refusal, and
 * the library after it.
 */
export interface AddAnswer {
    readonly added: readonly AddedSnippet[];
    readonly refused: AddRefusal | null;
    readonly library: Library;
}
