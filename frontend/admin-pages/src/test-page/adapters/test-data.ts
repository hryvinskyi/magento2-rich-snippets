/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The readers of the Test page's data and of its endpoints' answers, in the shapes of the shared contract
 * (`Test/_contract/admin-pages/test-*.json`): the page's first data, the picker's search, a page test, where the check
 * stands, the findings and the pages behind one. The first wrong field throws a `ContractError` naming its path.
 *
 * Only what the page shows is kept: a block's attribution and JSON but not the time it was tested, the theme's markup
 * as the server words it, a finished run's id and message.
 */
import { at, bool, filledText, finite, items, nullable, oneOf, record, text, wholeNumber } from '@hryvinskyi/admin-ui';
import {
    BLOCK_VERDICTS,
    CHECK_STATES,
    FEATURE_VERDICTS,
    FINDING_TONES,
    MODES,
    STATUS_TONES,
    type CheckStatus,
    type Feature,
    type Finding,
    type FindingItem,
    type FindingItems,
    type FindingsTable,
    type NotOutput,
    type PageTest,
    type PickedPage,
    type SearchAnswer,
    type TestData,
    type TestedBlock,
} from '../domain/model.ts';

const id = (value: unknown, path: string): number | null => nullable(value, path, wholeNumber);
const words = (value: unknown, path: string): string | null => nullable(value, path, text);

/** A page as the picker, the page's data and a test's answer name it. */
export function parsePickedPage(value: unknown, path: string): PickedPage {
    const fields = record(value, path);
    return {
        ref: words(fields['ref'], at(path, 'ref')),
        kind: filledText(fields['kind'], at(path, 'kind')),
        label: text(fields['label'], at(path, 'label')),
        path: text(fields['path'], at(path, 'path')),
    };
}

export function parseSearchAnswer(value: unknown, path: string): SearchAnswer {
    const fields = record(value, path);
    return {
        pages: items(fields['pages'], at(path, 'pages'), parsePickedPage),
        address: words(fields['address'], at(path, 'address')),
    };
}

function block(value: unknown, path: string): TestedBlock {
    const fields = record(value, path);
    return {
        code: words(fields['code'], at(path, 'code')),
        snippetId: id(fields['snippetId'], at(path, 'snippetId')),
        name: words(fields['name'], at(path, 'name')),
        type: text(fields['type'], at(path, 'type')),
        json: text(fields['json'], at(path, 'json')),
        size: wholeNumber(fields['size'], at(path, 'size')),
        lines: wholeNumber(fields['lines'], at(path, 'lines')),
        verdict: oneOf(BLOCK_VERDICTS, fields['verdict'], at(path, 'verdict')),
        summary: text(fields['summary'], at(path, 'summary')),
    };
}

function notOutput(value: unknown, path: string): NotOutput {
    const fields = record(value, path);
    return {
        code: filledText(fields['code'], at(path, 'code')),
        snippetId: id(fields['snippetId'], at(path, 'snippetId')),
        name: text(fields['name'], at(path, 'name')),
        reason: filledText(fields['reason'], at(path, 'reason')),
        text: text(fields['text'], at(path, 'text')),
    };
}

function feature(value: unknown, path: string): Feature {
    const fields = record(value, path);
    return {
        rule: filledText(fields['rule'], at(path, 'rule')),
        label: text(fields['label'], at(path, 'label')),
        verdict: oneOf(FEATURE_VERDICTS, fields['verdict'], at(path, 'verdict')),
        description: text(fields['description'], at(path, 'description')),
        missingRequired: items(fields['missingRequired'], at(path, 'missingRequired'), filledText),
        missingRecommended: items(fields['missingRecommended'], at(path, 'missingRecommended'), filledText),
    };
}

export function parsePageTest(value: unknown, path: string): PageTest {
    const fields = record(value, path);
    const failurePath = at(path, 'failure');
    const failure = nullable(fields['failure'], failurePath, (raw, where) => {
        const failed = record(raw, where);
        return {
            reason: filledText(failed['reason'], at(where, 'reason')),
            httpStatus: id(failed['httpStatus'], at(where, 'httpStatus')),
            text: text(failed['text'], at(where, 'text')),
        };
    });
    const themePath = at(path, 'themeMarkup');
    const theme = record(fields['themeMarkup'], themePath);
    return {
        page: parsePickedPage(fields['page'], at(path, 'page')),
        store: wholeNumber(fields['store'], at(path, 'store')),
        durationMs: wholeNumber(fields['durationMs'], at(path, 'durationMs')),
        failure,
        blocks: items(fields['blocks'], at(path, 'blocks'), block),
        themeMarkup: bool(theme['found'], at(themePath, 'found')) ? text(theme['text'], at(themePath, 'text')) : null,
        notOutput: items(fields['notOutput'], at(path, 'notOutput'), notOutput),
        features: items(fields['features'], at(path, 'features'), feature),
    };
}

export function parseCheckStatus(value: unknown, path: string): CheckStatus {
    const fields = record(value, path);
    return {
        state: oneOf(CHECK_STATES, fields['state'], at(path, 'state')),
        tone: oneOf(STATUS_TONES, fields['tone'], at(path, 'tone')),
        title: text(fields['title'], at(path, 'title')),
        note: text(fields['note'], at(path, 'note')),
        facts: items(fields['facts'], at(path, 'facts'), (raw, where) => {
            const fact = record(raw, where);
            return { label: text(fact['label'], at(where, 'label')), value: wholeNumber(fact['value'], at(where, 'value')) };
        }),
        progress: nullable(fields['progress'], at(path, 'progress'), (raw, where) => {
            const progress = record(raw, where);
            return {
                checked: wholeNumber(progress['checked'], at(where, 'checked')),
                total: wholeNumber(progress['total'], at(where, 'total')),
                percent: finite(progress['percent'], at(where, 'percent')),
                text: text(progress['text'], at(where, 'text')),
                left: words(progress['left'], at(where, 'left')),
            };
        }),
        last: nullable(fields['last'], at(path, 'last'), (raw, where) => {
            const last = record(raw, where);
            return { id: wholeNumber(last['id'], at(where, 'id')), toast: text(last['toast'], at(where, 'toast')) };
        }),
        failed: nullable(fields['failed'], at(path, 'failed'), (raw, where) => text(record(raw, where)['text'], at(where, 'text'))),
    };
}

function finding(value: unknown, path: string): Finding {
    const fields = record(value, path);
    return {
        key: filledText(fields['key'], at(path, 'key')),
        tone: oneOf(FINDING_TONES, fields['tone'], at(path, 'tone')),
        title: text(fields['title'], at(path, 'title')),
        description: text(fields['description'], at(path, 'description')),
        fix: text(fields['fix'], at(path, 'fix')),
        snippets: items(fields['snippets'], at(path, 'snippets'), (raw, where) => {
            const snippet = record(raw, where);
            return { code: filledText(snippet['code'], at(where, 'code')), name: text(snippet['name'], at(where, 'name')) };
        }),
        pages: wholeNumber(fields['pages'], at(path, 'pages')),
        allProducts: bool(fields['allProducts'], at(path, 'allProducts')),
        meta: text(fields['meta'], at(path, 'meta')),
        label: text(fields['label'], at(path, 'label')),
        saved: text(fields['saved'], at(path, 'saved')),
    };
}

export function parseFindingsTable(value: unknown, path: string): FindingsTable {
    const fields = record(value, path);
    return {
        store: id(fields['store'], at(path, 'store')),
        subtitle: text(fields['subtitle'], at(path, 'subtitle')),
        findings: items(fields['findings'], at(path, 'findings'), finding),
    };
}

function findingItem(value: unknown, path: string): FindingItem {
    const fields = record(value, path);
    return {
        ref: filledText(fields['ref'], at(path, 'ref')),
        kind: filledText(fields['kind'], at(path, 'kind')),
        id: id(fields['id'], at(path, 'id')),
        store: wholeNumber(fields['store'], at(path, 'store')),
        label: text(fields['label'], at(path, 'label')),
        sku: words(fields['sku'], at(path, 'sku')),
        group: words(fields['group'], at(path, 'group')),
    };
}

export function parseFindingItems(value: unknown, path: string): FindingItems {
    const fields = record(value, path);
    return {
        key: finding(fields['finding'], at(path, 'finding')).key,
        total: wholeNumber(fields['total'], at(path, 'total')),
        page: wholeNumber(fields['page'], at(path, 'page')),
        pageSize: wholeNumber(fields['pageSize'], at(path, 'pageSize')),
        note: words(fields['note'], at(path, 'note')),
        items: items(fields['items'], at(path, 'items'), findingItem),
    };
}

export function parseTestData(value: unknown, path: string): TestData {
    const fields = record(value, path);
    return {
        mode: oneOf(MODES, fields['mode'], at(path, 'mode')),
        store: wholeNumber(fields['store'], at(path, 'store')),
        page: nullable(fields['page'], at(path, 'page'), parsePickedPage),
        catalogStore: id(fields['catalogStore'], at(path, 'catalogStore')),
        finding: words(fields['finding'], at(path, 'finding')),
        schedule: text(fields['schedule'], at(path, 'schedule')),
        status: parseCheckStatus(fields['status'], at(path, 'status')),
        findings: parseFindingsTable(fields['findings'], at(path, 'findings')),
    };
}
