/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads what the server prints into the snippet editor and what the editor's catalog endpoints answer: the first data
 * (`{"snippet", "isNew", "section", "usedIn", "vocabulary", "kinds", "typeSuggestions", "defaults"}`), the field catalog, the
 * sample pages, the snippets the edited one may include, the builder's configuration and the placement count. The
 * only file that knows those shapes; nothing is cast, and the first wrong value throws a `ContractError` naming its
 * path.
 *
 * The server writes an object it has nothing to put in as an empty list (`[]`); a map read here takes that as empty.
 */
import { at, bool, filledText, isRecord, items, json, list, nullable, oneOf, record, text, wholeNumber } from '@hryvinskyi/admin-ui';
import type {
    CatalogField,
    CatalogList,
    ConditionField,
    Draft,
    EditorData,
    EditorSection,
    FieldCatalog,
    FieldGroup,
    IncludeCandidate,
    KindCount,
    PageKind,
    PlacementCount,
    Sample,
    SnippetOrigin,
    Suggestion,
    UsedIn,
    ValueDefaults,
} from '../domain/model.ts';

const SECTIONS: readonly EditorSection[] = ['properties', 'pages'];

/** An object of values read by `read`, keyed as the server sent them; an empty list counts as an empty object. */
function mapOf<T>(value: unknown, path: string, read: (item: unknown, path: string) => T): ReadonlyMap<string, T> {
    if (!isRecord(value) && list(value, path).length === 0) {
        return new Map();
    }
    return new Map(Object.entries(record(value, path)).map(([key, item]) => [key, read(item, at(path, key))]));
}

function origin(value: unknown, path: string): SnippetOrigin {
    const fields = record(value, path);
    return { code: filledText(fields['code'], at(path, 'code')), version: wholeNumber(fields['version'], at(path, 'version')) };
}

export function parseDraft(value: unknown, path: string): Draft {
    const fields = record(value, path);
    return {
        id: nullable(fields['id'], at(path, 'id'), wholeNumber),
        code: text(fields['code'], at(path, 'code')),
        name: text(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        template: text(fields['template'], at(path, 'template')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        active: bool(fields['active'], at(path, 'active')),
        pageKinds: items(fields['pageKinds'], at(path, 'pageKinds'), filledText),
        conditions: text(fields['conditions'], at(path, 'conditions')),
        storeIds: items(fields['storeIds'], at(path, 'storeIds'), wholeNumber),
        origin: nullable(fields['origin'], at(path, 'origin'), origin),
        updatedAt: nullable(fields['updatedAt'], at(path, 'updatedAt'), text),
        updatedBy: text(fields['updatedBy'], at(path, 'updatedBy')),
    };
}

function usedIn(value: unknown, path: string): UsedIn {
    const fields = record(value, path);
    return {
        id: nullable(fields['id'], at(path, 'id'), wholeNumber),
        code: filledText(fields['code'], at(path, 'code')),
        name: text(fields['name'], at(path, 'name')),
        paths: items(fields['paths'], at(path, 'paths'), text),
    };
}

function conditionField(value: unknown, path: string): ConditionField {
    const fields = record(value, path);
    return { code: filledText(fields['code'], at(path, 'code')), label: text(fields['label'], at(path, 'label')) };
}

function kind(value: unknown, path: string): PageKind {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        label: text(fields['label'], at(path, 'label')),
        phrase: text(fields['phrase'], at(path, 'phrase')),
        page: text(fields['page'], at(path, 'page')),
        icon: text(fields['icon'], at(path, 'icon')),
    };
}

function defaults(value: unknown, path: string): ValueDefaults {
    const fields = record(value, path);
    const listed = record(fields['list'], at(path, 'list'));
    return {
        text: text(fields['text'], at(path, 'text')),
        code: text(fields['code'], at(path, 'code')),
        when: text(fields['when'], at(path, 'when')),
        list: { each: text(listed['each'], at(at(path, 'list'), 'each')), item: text(listed['item'], at(at(path, 'list'), 'item')) },
    };
}

export function parseEditorData(value: unknown, path: string): EditorData {
    const fields = record(value, path);
    const vocabulary = record(fields['vocabulary'], at(path, 'vocabulary'));
    return {
        snippet: parseDraft(fields['snippet'], at(path, 'snippet')),
        isNew: bool(fields['isNew'], at(path, 'isNew')),
        section: oneOf(SECTIONS, fields['section'], at(path, 'section')),
        usedIn: items(fields['usedIn'], at(path, 'usedIn'), usedIn),
        conditions: mapOf(vocabulary['conditions'], at(at(path, 'vocabulary'), 'conditions'), (item, itemPath) => items(item, itemPath, conditionField)),
        kinds: items(fields['kinds'], at(path, 'kinds'), kind),
        typeSuggestions: items(fields['typeSuggestions'], at(path, 'typeSuggestions'), filledText),
        defaults: defaults(fields['defaults'], at(path, 'defaults')),
    };
}

export function parseSample(value: unknown, path: string): Sample {
    const fields = record(value, path);
    return {
        ref: filledText(fields['ref'], at(path, 'ref')),
        kind: filledText(fields['kind'], at(path, 'kind')),
        id: nullable(fields['id'], at(path, 'id'), wholeNumber),
        label: text(fields['label'], at(path, 'label')),
        url: text(fields['url'], at(path, 'url')),
        path: text(fields['path'], at(path, 'path')),
    };
}

/** What a field holds on the sample page, as text: a list or an object as JSON. */
function example(value: unknown, path: string): string | null {
    const read = json(value, path);
    if (read === null) {
        return null;
    }
    return typeof read === 'string' ? read : JSON.stringify(read);
}

function catalogField(value: unknown, path: string): CatalogField {
    const fields = record(value, path);
    return {
        path: filledText(fields['path'], at(path, 'path')),
        label: text(fields['label'], at(path, 'label')),
        attributeCode: nullable(fields['attributeCode'], at(path, 'attributeCode'), text),
        pageKinds: items(fields['pageKinds'], at(path, 'pageKinds'), filledText),
        example: example(fields['example'], at(path, 'example')),
    };
}

function group(value: unknown, path: string): FieldGroup {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        label: text(fields['label'], at(path, 'label')),
        fields: items(fields['fields'], at(path, 'fields'), catalogField),
    };
}

function catalogList(value: unknown, path: string): CatalogList {
    const fields = record(value, path);
    return {
        path: filledText(fields['path'], at(path, 'path')),
        label: text(fields['label'], at(path, 'label')),
        itemName: filledText(fields['itemName'], at(path, 'itemName')),
    };
}

function suggestion(value: unknown, path: string): Suggestion {
    const fields = record(value, path);
    return {
        name: filledText(fields['name'], at(path, 'name')),
        required: bool(fields['required'], at(path, 'required')),
        field: nullable(fields['field'], at(path, 'field'), text),
    };
}

export function parseFieldCatalog(value: unknown, path: string): FieldCatalog {
    const fields = record(value, path);
    return {
        groups: items(fields['groups'], at(path, 'groups'), group),
        lists: items(fields['lists'], at(path, 'lists'), catalogList),
        enums: mapOf(fields['enums'], at(path, 'enums'), (item, itemPath) => items(item, itemPath, text)),
        suggestions: mapOf(fields['suggestions'], at(path, 'suggestions'), (byType, typePath) =>
            mapOf(byType, typePath, (item, itemPath) => items(item, itemPath, suggestion))),
        namedFields: mapOf(fields['namedFields'], at(path, 'namedFields'), (byType, typePath) =>
            mapOf(byType, typePath, (item, itemPath) => filledText(item, itemPath))),
        sample: nullable(fields['sample'], at(path, 'sample'), parseSample),
    };
}

export function parseSamples(value: unknown, path: string): readonly Sample[] {
    return items(record(value, path)['samples'], at(path, 'samples'), parseSample);
}

function candidate(value: unknown, path: string): IncludeCandidate {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        name: text(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        isBlock: bool(fields['isBlock'], at(path, 'isBlock')),
        description: text(fields['description'], at(path, 'description')),
        loops: bool(fields['loops'], at(path, 'loops')),
    };
}

export function parseCandidates(value: unknown, path: string): readonly IncludeCandidate[] {
    return items(record(value, path)['candidates'], at(path, 'candidates'), candidate);
}

export function parseBuilderConfig(value: unknown, path: string): Readonly<Record<string, unknown>> {
    return record(record(value, path)['config'], at(path, 'config'));
}

function kindCount(value: unknown, path: string): KindCount {
    const fields = record(value, path);
    return { matching: wholeNumber(fields['matching'], at(path, 'matching')), total: wholeNumber(fields['total'], at(path, 'total')) };
}

export function parsePlacementCount(value: unknown, path: string): PlacementCount {
    const fields = record(value, path);
    return {
        kinds: mapOf(fields['kinds'], at(path, 'kinds'), kindCount),
        matching: wholeNumber(fields['matching'], at(path, 'matching')),
        total: wholeNumber(fields['total'], at(path, 'total')),
        sentence: text(fields['sentence'], at(path, 'sentence')),
        count: text(fields['count'], at(path, 'count')),
        countLine: text(fields['countLine'], at(path, 'countLine')),
        asked: nullable(fields['asked'], at(path, 'asked'), text),
    };
}
