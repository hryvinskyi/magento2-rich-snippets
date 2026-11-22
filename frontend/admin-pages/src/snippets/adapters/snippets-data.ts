/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads what the server prints into the Snippets page and what its endpoints answer: the first data (`{"kinds",
 * "outputLine", "list"}`), the list (`{"rows", "counts"}`), the answer to a write on one snippet (the list with `"id"`
 * and `"refused"`), the import preview and the answer to an import. The only file that knows those shapes; nothing is
 * cast, and the first wrong value throws a `ContractError` naming its path.
 *
 * The server writes an object it has nothing to put in as an empty list (`[]`); a map read here takes that as empty.
 */
import {
    at,
    bool,
    filledText,
    isRecord,
    items,
    list,
    nullable,
    oneOf,
    record,
    text,
    wholeNumber,
} from '@hryvinskyi/admin-ui';
import {
    IMPORT_CHOICES,
    IMPORT_STATUSES,
    type ImportAnswer,
    type ImportEntry,
    type ImportPreview,
    type ImportProblem,
    type Listing,
    type PageKind,
    type Refusal,
    type SnippetCounts,
    type SnippetFlags,
    type SnippetReference,
    type SnippetRow,
    type SnippetsData,
    type WriteAnswer,
} from '../domain/model.ts';

/** An object of values read by `read`, keyed as the server sent them; an empty list counts as an empty object. */
function mapOf<T>(value: unknown, path: string, read: (item: unknown, path: string) => T): ReadonlyMap<string, T> {
    if (!isRecord(value) && list(value, path).length === 0) {
        return new Map();
    }
    return new Map(Object.entries(record(value, path)).map(([key, item]) => [key, read(item, at(path, key))]));
}

function reference(value: unknown, path: string): SnippetReference {
    const fields = record(value, path);
    return { code: filledText(fields['code'], at(path, 'code')), name: nullable(fields['name'], at(path, 'name'), text) };
}

function flags(value: unknown, path: string): SnippetFlags {
    const fields = record(value, path);
    return {
        bad: nullable(fields['bad'], at(path, 'bad'), text),
        warn: nullable(fields['warn'], at(path, 'warn'), text),
        note: nullable(fields['note'], at(path, 'note'), text),
    };
}

function row(value: unknown, path: string): SnippetRow {
    const fields = record(value, path);
    return {
        id: wholeNumber(fields['id'], at(path, 'id')),
        code: filledText(fields['code'], at(path, 'code')),
        name: text(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        active: bool(fields['active'], at(path, 'active')),
        pageKinds: items(fields['pageKinds'], at(path, 'pageKinds'), filledText),
        conditionSummary: text(fields['conditionSummary'], at(path, 'conditionSummary')),
        storeIds: items(fields['storeIds'], at(path, 'storeIds'), wholeNumber),
        storeNames: items(fields['storeNames'], at(path, 'storeNames'), text),
        propertyCount: wholeNumber(fields['propertyCount'], at(path, 'propertyCount')),
        uses: items(fields['uses'], at(path, 'uses'), reference),
        usedIn: items(fields['usedIn'], at(path, 'usedIn'), reference),
        updatedAt: nullable(fields['updatedAt'], at(path, 'updatedAt'), filledText),
        updatedBy: text(fields['updatedBy'], at(path, 'updatedBy')),
        flags: flags(fields['flags'], at(path, 'flags')),
    };
}

function counts(value: unknown, path: string): SnippetCounts {
    const fields = record(value, path);
    return {
        all: wholeNumber(fields['all'], at(path, 'all')),
        attention: wholeNumber(fields['attention'], at(path, 'attention')),
        kinds: mapOf(fields['kinds'], at(path, 'kinds'), wholeNumber),
        blocks: wholeNumber(fields['blocks'], at(path, 'blocks')),
        on: wholeNumber(fields['on'], at(path, 'on')),
        off: wholeNumber(fields['off'], at(path, 'off')),
    };
}

/** The snippet list for one store view or every one. */
export function parseListing(value: unknown, path: string): Listing {
    const fields = record(value, path);
    return {
        rows: items(fields['rows'], at(path, 'rows'), row),
        counts: counts(fields['counts'], at(path, 'counts')),
    };
}

function kind(value: unknown, path: string): PageKind {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        label: filledText(fields['label'], at(path, 'label')),
        phrase: filledText(fields['phrase'], at(path, 'phrase')),
        icon: filledText(fields['icon'], at(path, 'icon')),
    };
}

/** The page's first data. */
export function parseSnippetsData(value: unknown, path: string): SnippetsData {
    const fields = record(value, path);
    return {
        kinds: items(fields['kinds'], at(path, 'kinds'), kind),
        outputLine: text(fields['outputLine'], at(path, 'outputLine')),
        list: parseListing(fields['list'], at(path, 'list')),
    };
}

function refusal(value: unknown, path: string): Refusal {
    const fields = record(value, path);
    return {
        reason: filledText(fields['reason'], at(path, 'reason')),
        includers: items(fields['includers'], at(path, 'includers'), reference),
    };
}

/** The answer to a switch, a copy or a delete. */
export function parseWriteAnswer(value: unknown, path: string): WriteAnswer {
    const fields = record(value, path);
    return {
        list: parseListing(value, path),
        id: nullable(fields['id'], at(path, 'id'), wholeNumber),
        refused: nullable(fields['refused'], at(path, 'refused'), refusal),
    };
}

function problem(value: unknown, path: string): ImportProblem {
    const fields = record(value, path);
    return {
        reason: filledText(fields['reason'], at(path, 'reason')),
        params: mapOf(fields['params'], at(path, 'params'), text),
    };
}

function entry(value: unknown, path: string): ImportEntry {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        name: text(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        status: oneOf(IMPORT_STATUSES, fields['status'], at(path, 'status')),
        existingId: nullable(fields['existingId'], at(path, 'existingId'), wholeNumber),
        changedHere: nullable(fields['changedHere'], at(path, 'changedHere'), filledText),
        changedInFile: nullable(fields['changedInFile'], at(path, 'changedInFile'), filledText),
        choices: items(fields['choices'], at(path, 'choices'), (choice, where) => oneOf(IMPORT_CHOICES, choice, where)),
        problems: items(fields['problems'], at(path, 'problems'), problem),
        pageKinds: items(fields['pageKinds'], at(path, 'pageKinds'), filledText),
        conditionSummary: text(fields['conditionSummary'], at(path, 'conditionSummary')),
    };
}

/** What importing a file would do. */
export function parseImportPreview(value: unknown, path: string): ImportPreview {
    const fields = record(value, path);
    return {
        exportedAt: text(fields['exportedAt'], at(path, 'exportedAt')),
        source: text(fields['source'], at(path, 'source')),
        snippets: items(fields['snippets'], at(path, 'snippets'), entry),
    };
}

/** The answer to an import: the list after it, and how many snippets it added or replaced. */
export function parseImportAnswer(value: unknown, path: string): ImportAnswer {
    const fields = record(value, path);
    const imported = items(fields['imported'], at(path, 'imported'), (item, where) => {
        const written = record(item, where);
        return oneOf(IMPORT_CHOICES, written['choice'], at(where, 'choice'));
    });
    return {
        list: parseListing(value, path),
        imported: imported.filter((choice) => choice !== 'skip').length,
    };
}
