/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads what the server prints into the Library page and what its endpoints answer: the first data (the library with
 * `"open"`), the library (`{"groups", "recommended", "templates"}`), the questions of a template (`{"template",
 * "questions"}`) and the answer to an add (`{"added", "refused", "library"}`). The only file that knows those shapes;
 * nothing is cast, and the first wrong value throws a `ContractError` naming its path.
 */
import { at, bool, filledText, items, nullable, oneOf, record, text, wholeNumber } from '@hryvinskyi/admin-ui';
import {
    GOOGLE_STATUSES,
    HINT_TONES,
    type AddAnswer,
    type AddedSnippet,
    type AnswerOption,
    type Library,
    type LibraryData,
    type LibraryGroup,
    type LibraryTemplate,
    type QuestionAnswers,
    type TemplateAnswers,
    type TemplateQuestion,
    type TemplateUse,
} from '../domain/model.ts';

function group(value: unknown, path: string): LibraryGroup {
    const fields = record(value, path);
    return { code: filledText(fields['code'], at(path, 'code')), label: filledText(fields['label'], at(path, 'label')) };
}

function use(value: unknown, path: string): TemplateUse {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        name: filledText(fields['name'], at(path, 'name')),
        inShop: bool(fields['inShop'], at(path, 'inShop')),
    };
}

function question(value: unknown, path: string): TemplateQuestion {
    const fields = record(value, path);
    return { code: filledText(fields['code'], at(path, 'code')), label: filledText(fields['label'], at(path, 'label')) };
}

function template(value: unknown, path: string): LibraryTemplate {
    const fields = record(value, path);
    const googlePath = at(path, 'google');
    const google = record(fields['google'], googlePath);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        version: wholeNumber(fields['version'], at(path, 'version')),
        group: filledText(fields['group'], at(path, 'group')),
        name: filledText(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        icon: filledText(fields['icon'], at(path, 'icon')),
        description: text(fields['description'], at(path, 'description')),
        summary: text(fields['summary'], at(path, 'summary')),
        where: text(fields['where'], at(path, 'where')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        google: {
            status: oneOf(GOOGLE_STATUSES, google['status'], at(googlePath, 'status')),
            short: text(google['short'], at(googlePath, 'short')),
            long: text(google['long'], at(googlePath, 'long')),
        },
        properties: items(fields['properties'], at(path, 'properties'), filledText),
        uses: items(fields['uses'], at(path, 'uses'), use),
        questions: items(fields['questions'], at(path, 'questions'), question),
        added: bool(fields['added'], at(path, 'added')),
        hasNewerVersion: bool(fields['hasNewerVersion'], at(path, 'hasNewerVersion')),
        nextName: filledText(fields['nextName'], at(path, 'nextName')),
    };
}

/** The library with what the shop has of it. */
export function parseLibrary(value: unknown, path: string): Library {
    const fields = record(value, path);
    return {
        groups: items(fields['groups'], at(path, 'groups'), group),
        recommended: items(fields['recommended'], at(path, 'recommended'), filledText),
        templates: items(fields['templates'], at(path, 'templates'), template),
    };
}

/** The page's first data: the library, and the template whose drawer opens first. */
export function parseLibraryData(value: unknown, path: string): LibraryData {
    const fields = record(value, path);
    return {
        library: parseLibrary(value, path),
        open: nullable(fields['open'], at(path, 'open'), filledText),
        unavailable: bool(fields['unavailable'], at(path, 'unavailable')),
    };
}

function option(value: unknown, path: string): AnswerOption {
    const fields = record(value, path);
    return {
        key: filledText(fields['key'], at(path, 'key')),
        label: filledText(fields['label'], at(path, 'label')),
        leftOut: bool(fields['leftOut'], at(path, 'leftOut')),
        hint: text(fields['hint'], at(path, 'hint')),
        tone: oneOf(HINT_TONES, fields['tone'], at(path, 'tone')),
        fillCount: nullable(fields['fillCount'], at(path, 'fillCount'), wholeNumber),
    };
}

function answers(value: unknown, path: string): QuestionAnswers {
    const fields = record(value, path);
    return {
        question: filledText(fields['question'], at(path, 'question')),
        label: filledText(fields['label'], at(path, 'label')),
        default: filledText(fields['default'], at(path, 'default')),
        total: nullable(fields['total'], at(path, 'total'), wholeNumber),
        options: items(fields['options'], at(path, 'options'), option),
    };
}

/** The questions of a template with the answers this shop is offered. */
export function parseTemplateAnswers(value: unknown, path: string): TemplateAnswers {
    const fields = record(value, path);
    return {
        template: filledText(fields['template'], at(path, 'template')),
        questions: items(fields['questions'], at(path, 'questions'), answers),
    };
}

function added(value: unknown, path: string): AddedSnippet {
    const fields = record(value, path);
    return {
        id: wholeNumber(fields['id'], at(path, 'id')),
        code: filledText(fields['code'], at(path, 'code')),
        name: filledText(fields['name'], at(path, 'name')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        template: filledText(fields['template'], at(path, 'template')),
        reused: bool(fields['reused'], at(path, 'reused')),
    };
}

/** The answer to an add of one template or of the recommended set. */
export function parseAddAnswer(value: unknown, path: string): AddAnswer {
    const fields = record(value, path);
    return {
        added: items(fields['added'], at(path, 'added'), added),
        refused: nullable(fields['refused'], at(path, 'refused'), (refusal, where) => ({
            reason: filledText(record(refusal, where)['reason'], at(where, 'reason')),
        })),
        library: parseLibrary(fields['library'], at(path, 'library')),
    };
}
