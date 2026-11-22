/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the page says around adding: the recommended set the first visit offers, what the add drawer warns about and
 * the answer each question shows as chosen, and the message an add ends with. Pure: phrases chosen from what the
 * server says, and links to the screens the message offers.
 *
 * - The recommended set shows while one of its templates is not added yet, the templates in the order they are added.
 * - The drawer warns when the shop has the template already: that the new version is added beside the copy when the
 *   library has a newer one, a warning of the template's own when it has one (two Product snippets output two Product
 *   blocks), else that the copy is added beside it under its next name.
 * - Each question shows the answer the admin chose, else the one suggested in advance.
 * - An add ends with the copy's name and a link to its editor, worded for a building block when it is one; the
 *   recommended set ends with how many snippets and building blocks it created, and a link to the page test.
 */
import { linkTo, phrase, withParam, type Phrase, type ScreenLink } from '@hryvinskyi/admin-ui';
import type { AddAnswer, AddedSnippet, AnswerOption, Library, LibraryTemplate, QuestionAnswers, TemplateUse } from './model.ts';

export const EDITOR = 'hryvinskyi_richsnippets/snippets/edit';
export const TEST = 'hryvinskyi_richsnippets/test/index';

/** A sentence that may come in plural forms: `count` picks the form, and is its first value. */
export interface Sentence {
    readonly phrase: Phrase;
    readonly count: number | null;
}

/** A message about an add: its sentence, and the screen it offers to go on to with the words of the link. */
export interface AddMessage {
    readonly sentence: Sentence;
    readonly link: { readonly to: ScreenLink; readonly label: Phrase } | null;
}

const plain = (key: string, ...params: readonly string[]): Sentence => ({ phrase: phrase(key, ...params), count: null });

/** The templates of the recommended set, in the order they are added; empty when every one is added already. */
export function recommendedSetOf(library: Library): readonly LibraryTemplate[] {
    const byCode = new Map(library.templates.map((template) => [template.code, template]));
    const set = library.recommended
        .map((code) => byCode.get(code))
        .filter((template): template is LibraryTemplate => template !== undefined);
    return set.every((template) => template.added) ? [] : set;
}

/** Templates with a warning of their own when the shop has one already. */
const AGAIN: ReadonlyMap<string, string> = new Map([['product', 'library.again.product']]);

/** What the drawer warns about before a template the shop has is added again; null for one it does not have. */
export function againNoticeOf(template: LibraryTemplate): Phrase | null {
    if (template.hasNewerVersion) {
        return phrase('library.drawer.newer', template.nextName);
    }
    if (!template.added) {
        return null;
    }
    const own = AGAIN.get(template.code);
    return own === undefined ? phrase('library.again.other', template.name, template.nextName) : phrase(own);
}

/** The words of the drawer's button: the new version beside the copy, or an add switched off. */
export const addButtonOf = (template: LibraryTemplate): Phrase =>
    phrase(template.hasNewerVersion ? 'library.drawer.addNewer' : 'library.drawer.add');

/** What the drawer says of a building block the template uses. */
export const useLineOf = (use: TemplateUse): Phrase => phrase(use.inShop ? 'library.blocks.have' : 'library.blocks.new');

/** The answer a question shows as chosen: the admin's choice, else the one suggested; null for a question with none. */
export function chosenOptionOf(question: QuestionAnswers, chosen: ReadonlyMap<string, string>): AnswerOption | null {
    const key = chosen.get(question.question) ?? question.default;
    return question.options.find((option) => option.key === key)
        ?? question.options.find((option) => option.key === question.default)
        ?? null;
}

/** The answer each question is added with: the admin's choice, else the one suggested. */
export function answersToSend(questions: readonly QuestionAnswers[], chosen: ReadonlyMap<string, string>): ReadonlyMap<string, string> {
    return new Map(questions.flatMap((question) => {
        const option = chosenOptionOf(question, chosen);
        return option === null ? [] : [[question.question, option.key] as const];
    }));
}

/** The copy an add of a template made: the last snippet it created for that template. */
const copyOf = (answer: AddAnswer, template: string): AddedSnippet | null =>
    [...answer.added].reverse().find((snippet) => snippet.template === template && !snippet.reused) ?? null;

/** The message an add of one template ends with. */
export function addedMessageOf(answer: AddAnswer, template: LibraryTemplate): AddMessage {
    const copy = copyOf(answer, template.code);
    const name = copy?.name ?? template.nextName;
    return {
        sentence: plain(template.isBlock ? 'library.toast.addedBlock' : 'library.toast.added', name),
        link: copy === null ? null : {
            to: withParam(linkTo(EDITOR), 'id', String(copy.id)),
            label: phrase('library.toast.open'),
        },
    };
}

/** The message the recommended set ends with: how many snippets, and how many building blocks they share, it created. */
export function setMessageOf(answer: AddAnswer): AddMessage {
    const created = answer.added.filter((snippet) => !snippet.reused);
    const copies = created.filter((snippet) => !snippet.isBlock).length;
    const blocks = created.length - copies;
    if (copies === 0) {
        return { sentence: plain('library.toast.setNone'), link: null };
    }
    const sentence: Sentence = blocks > 0
        ? { phrase: phrase('library.toast.setBlocks', String(copies)), count: blocks }
        : { phrase: phrase('library.toast.set'), count: copies };
    return { sentence, link: { to: linkTo(TEST), label: phrase('library.toast.openTest') } };
}

/** The reasons an add is refused for that the page has words of their own for. */
const REFUSALS: ReadonlyMap<string, string> = new Map([['template_invalid', 'library.refused.template_invalid']]);

/** What the page says about a refused add: the reason's own words, or the general ones. */
export const refusalOf = (reason: string): Phrase => phrase(REFUSALS.get(reason) ?? 'library.refused.other');
