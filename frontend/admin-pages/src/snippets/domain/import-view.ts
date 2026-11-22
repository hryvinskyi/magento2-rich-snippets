/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the import drawer says about a file and about each snippet in it, and what it would import. Pure: the
 * sentences are put together through the page's words and dates, from the server's preview.
 *
 * - Each snippet's status line (new here, changed here and in the file with both dates, identical, cannot be
 *   imported) and the problems the server found in it.
 * - The effect of each choice: a replaced snippet keeps its switch (so it "stays on" or "stays off", as the shop's
 *   copy is), a copy kept beside it is "{name} (imported)" and arrives off, an added one arrives off and is set for
 *   its kinds of page under its conditions, a skipped one is left alone or not imported.
 * - The choices offered by default (the first of each snippet's), how many snippets the choices import, and the
 *   import button's words.
 * - What the drawer says when the server refuses the file as a whole.
 */
import { phrase, type Phrase } from '@hryvinskyi/admin-ui';
import type { ImportChoice, ImportEntry, ImportPreview, ImportProblem, ImportStatus, PageKind } from './model.ts';
import { kindsPhrase } from './row-view.ts';
import { joinParts, type Days, type Words } from './wording.ts';

/** The choice made for each snippet that offers one, by code. */
export type ImportChoices = ReadonlyMap<string, ImportChoice>;

/** The choices offered by default: the first one of each snippet that offers any. */
export function defaultChoices(preview: ImportPreview): ImportChoices {
    const choices = new Map<string, ImportChoice>();
    for (const entry of preview.snippets) {
        const first = entry.choices[0];
        if (first !== undefined) {
            choices.set(entry.code, first);
        }
    }
    return choices;
}

/** How many snippets the choices add or replace. */
export const importedCount = (choices: ImportChoices): number =>
    [...choices.values()].filter((choice) => choice !== 'skip').length;

/** The import button's words: how many snippets it imports, or the bare word while it imports none. */
export const runLabel = (count: number, words: Words): string =>
    count === 0 ? words.t('snippets.import.run') : words.tn('snippets.import.runCount', count);

const STATUSES: { readonly [S in ImportStatus]: (entry: ImportEntry, words: Words, days: Days) => string } = {
    new: (_entry, words) => words.t('snippets.import.status.new'),
    changed: (entry, words, days) => (entry.changedHere === null || entry.changedInFile === null
        ? words.t('snippets.import.status.changedUndated')
        : words.t('snippets.import.status.changed', days.long(entry.changedHere), days.long(entry.changedInFile))),
    identical: (_entry, words) => words.t('snippets.import.status.identical'),
    invalid: (_entry, words) => words.t('snippets.import.status.invalid'),
};

/** How a snippet of the file stands against the shop's snippet with its code. */
export const statusLine = (entry: ImportEntry, words: Words, days: Days): string =>
    STATUSES[entry.status](entry, words, days);

const param = (problem: ImportProblem, name: string): string => problem.params.get(name) ?? '';

/** The problems the page has words of its own for, each with the values its reason names. */
const PROBLEMS: ReadonlyMap<string, (problem: ImportProblem) => Phrase> = new Map([
    ['condition_unresolved', (problem: ImportProblem): Phrase => phrase(
        'snippets.import.problem.condition_unresolved',
        param(problem, 'label') === '' ? param(problem, 'value') : param(problem, 'label'),
    )],
    ['include_loop', (problem: ImportProblem): Phrase => phrase('snippets.import.problem.include_loop', param(problem, 'codes'))],
    ['include_missing', (problem: ImportProblem): Phrase => phrase('snippets.import.problem.include_missing', param(problem, 'code'))],
    ['store_unknown', (problem: ImportProblem): Phrase => phrase('snippets.import.problem.store_unknown', param(problem, 'store'))],
    ['template_invalid', (problem: ImportProblem): Phrase => phrase(
        'snippets.import.problem.template_invalid',
        param(problem, 'path'),
        param(problem, 'error'),
    )],
]);

/** One problem the server found in a snippet of the file: its reason's own words, or the general ones. */
export const problemOf = (problem: ImportProblem): Phrase =>
    PROBLEMS.get(problem.reason)?.(problem) ?? phrase('snippets.import.problem.other');

/** What the shop knows about its own snippet with the entry's code: whether it is on; null when it cannot tell. */
export type ExistingSwitch = boolean | null;

const EFFECTS: {
    readonly [C in ImportChoice]: (
        entry: ImportEntry,
        existing: ExistingSwitch,
        kinds: ReadonlyMap<string, PageKind>,
        words: Words,
    ) => string
} = {
    replace: (entry, existing, _kinds, words) => {
        if (entry.isBlock || existing === null) {
            return words.t('snippets.import.effect.replaceBlock');
        }
        return words.t(existing ? 'snippets.import.effect.replaceOn' : 'snippets.import.effect.replaceOff');
    },
    keep_both: (entry, _existing, _kinds, words) =>
        words.t(entry.isBlock ? 'snippets.import.effect.keepBothBlock' : 'snippets.import.effect.keepBoth', entry.name),
    add: (entry, _existing, kinds, words) => {
        if (entry.isBlock) {
            return words.t('snippets.import.effect.addBlock');
        }
        if (entry.pageKinds.length === 0) {
            return words.t('snippets.import.effect.addNowhere');
        }
        const pages = kindsPhrase(entry.pageKinds, kinds, words);
        return entry.conditionSummary === ''
            ? words.t('snippets.import.effect.add', pages)
            : words.t('snippets.import.effect.addWhere', pages, entry.conditionSummary);
    },
    skip: (entry, _existing, _kinds, words) =>
        words.t(entry.existingId === null ? 'snippets.import.effect.skipNew' : 'snippets.import.effect.skipExisting'),
};

/** What a choice does to the shop, in one line. */
export const effectLine = (
    entry: ImportEntry,
    choice: ImportChoice,
    existing: ExistingSwitch,
    kinds: ReadonlyMap<string, PageKind>,
    words: Words,
): string => EFFECTS[choice](entry, existing, kinds, words);

/** The line under the file's name: how many snippets, when and where it was exported. */
export function fileMeta(preview: ImportPreview, words: Words, days: Days): string {
    const exported = preview.exportedAt === ''
        ? ''
        : preview.source === ''
            ? words.t('snippets.import.file.exported', days.full(preview.exportedAt))
            : words.t('snippets.import.file.exportedFrom', days.full(preview.exportedAt), preview.source);
    return joinParts([words.tn('snippets.import.file.count', preview.snippets.length), exported]);
}

/** A file's size as the drawer shows it, in whole kilobytes, never less than one. */
export const fileSize = (bytes: number, words: Words): string =>
    words.t('snippets.import.file.kilobytes', words.n(Math.max(1, Math.ceil(bytes / 1024))));

/** The reasons the server refuses a whole file for that the page has words of its own for. */
const FILE_REFUSALS: ReadonlyMap<string, string> = new Map([
    ['not_an_export', 'snippets.import.refused.not_an_export'],
    ['not_json', 'snippets.import.refused.not_json'],
    ['file_too_large', 'snippets.import.refused.file_too_large'],
    ['too_deep', 'snippets.import.refused.too_deep'],
    ['version_unsupported', 'snippets.import.refused.version_unsupported'],
    ['snippet_malformed', 'snippets.import.refused.snippet_malformed'],
    ['code_repeated', 'snippets.import.refused.code_repeated'],
    // Refused at the commit although the preview offered it: the shop or the file changed in between.
    ['choice_unknown', 'snippets.import.refused.changed'],
    ['choice_not_offered', 'snippets.import.refused.changed'],
    ['include_loop', 'snippets.import.refused.changed'],
    ['rule_broken', 'snippets.import.refused.changed'],
    ['template_invalid', 'snippets.import.refused.changed'],
]);

/** What the drawer says about a file the server refused, for the reason it gave; null when it gave none it knows. */
export const fileRefusalOf = (reason: string | null): Phrase | null => {
    const key = reason === null ? undefined : FILE_REFUSALS.get(reason);
    return key === undefined ? null : phrase(key);
};
