/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's data as the server prints and answers it, for tests: the snippet of the shared load contract
 * (`Test/_contract/admin-pages/editor-load.json`) or a building block, the catalogs of the shared catalog contract
 * (`Test/_contract/admin-pages/editor-catalogs.json`), both written by the server's own tests, and the page document
 * the server prints around them. Each call returns a fresh copy.
 */
import { readFileSync } from 'node:fs';
import { STORES } from './fixtures.ts';
import { KINDS } from './snippets-fixtures.ts';

const LOAD = new URL('../../../../Test/_contract/admin-pages/editor-load.json', import.meta.url);
const CATALOGS = new URL('../../../../Test/_contract/admin-pages/editor-catalogs.json', import.meta.url);
const DRAFT = new URL('../../../../Test/_contract/admin-pages/editor-draft.json', import.meta.url);
const SAVE = new URL('../../../../Test/_contract/admin-pages/editor-save.json', import.meta.url);

/** The label of one page of each kind, as the editor's page prints it beside the kinds. */
const PAGE_LABELS: Readonly<Record<string, string>> = Object.freeze({
    home: 'Home page',
    product: 'Product page',
    category: 'Category page',
    cms: 'CMS page',
    other: 'Other page',
});

/** The kinds of page as the editor's page prints them. */
export const EDITOR_KINDS = Object.freeze(KINDS.map((kind) => ({ ...kind, page: PAGE_LABELS[kind.code] ?? kind.label })));

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function objectAt(value: unknown, key: string, file: string): Record<string, unknown> {
    const found = isObject(value) ? value[key] : undefined;
    if (!isObject(found)) {
        throw new Error(`${file} holds no "${key}"`);
    }
    return found;
}

const contract = (url: URL): unknown => JSON.parse(readFileSync(url, 'utf8'));

/** What the preview endpoint answers in one of its states, as the draft contract holds it. */
export const previewAnswer = (state: 'output' | 'conditionsNotMet' | 'noType'): Record<string, unknown> =>
    objectAt(objectAt(contract(DRAFT), 'preview', 'editor-draft.json'), state, 'editor-draft.json');

/** What the save endpoint answers in one of its outcomes, as the save contract holds it. */
export const saveAnswer = (outcome: 'saved' | 'refused' | 'conflict'): Record<string, unknown> =>
    objectAt(contract(SAVE), outcome, 'editor-save.json');

/** What a catalog endpoint answers, as the catalog contract holds it. */
export const catalogAnswer = (name: 'fields' | 'builderConfig' | 'placementCount' | 'samples' | 'includeCandidates'): Record<string, unknown> =>
    objectAt(contract(CATALOGS), name, 'editor-catalogs.json');

export const EDITOR_URLS: Readonly<Record<string, string>> = Object.freeze(Object.fromEntries(
    ['load', 'save', 'validate', 'preview', 'fields', 'samples', 'includeCandidates', 'builderConfig', 'placementCount']
        .map((name, index) => [name, `https://admin.test/admin/richsnippets/snippets/${name}/key/u${String(index)}/`]),
));

export const EDITOR_ROUTES: Readonly<Record<string, string>> = Object.freeze({
    'hryvinskyi_richsnippets/snippets/index': 'https://admin.test/admin/richsnippets/snippets/index/key/i1/',
    'hryvinskyi_richsnippets/snippets/edit': 'https://admin.test/admin/richsnippets/snippets/edit/key/e1/',
    'hryvinskyi_richsnippets/library/index': 'https://admin.test/admin/richsnippets/library/index/key/l1/',
    'hryvinskyi_richsnippets/test/index': 'https://admin.test/admin/richsnippets/test/index/key/t1/',
});

export const DEFAULTS = Object.freeze({
    text: '{{product.name}} by {{store.name}}',
    code: '{"@if": "product.on_sale", "@value": "{{product.special_to_date | date}}"}',
    when: 'product.on_sale',
    list: { each: 'product.gallery', item: 'image.url' },
});

export const TYPE_SUGGESTIONS = Object.freeze(['Product', 'OnlineStore', 'Store', 'BreadcrumbList', 'Article', 'VideoObject', 'Event']);

/** The editor's first data around the contract's product snippet, with the parts of the snippet given replaced. */
export function editorData(snippet: Readonly<Record<string, unknown>> = {}, rest: Readonly<Record<string, unknown>> = {}): Record<string, unknown> {
    const load = contract(LOAD);
    const data = objectAt(load, 'data', 'editor-load.json');
    return {
        snippet: { ...objectAt(data, 'snippet', 'editor-load.json'), ...snippet },
        isNew: false,
        section: 'properties',
        usedIn: data['usedIn'],
        vocabulary: data['vocabulary'],
        kinds: EDITOR_KINDS,
        typeSuggestions: TYPE_SUGGESTIONS,
        defaults: DEFAULTS,
        ...rest,
    };
}

/** A building block used by the snippets of the load contract's block case. */
export function blockData(): Record<string, unknown> {
    const block = objectAt(contract(LOAD), 'block', 'editor-load.json');
    return editorData({
        id: 9,
        code: 'return-policy',
        name: 'Return policy',
        type: 'MerchantReturnPolicy',
        template: '{\n  "@type": "MerchantReturnPolicy",\n  "merchantReturnDays": 30\n}',
        isBlock: true,
        active: false,
        pageKinds: [],
        conditions: '',
    }, { usedIn: block['usedIn'] });
}

/** A snippet not saved yet, as the new-snippet page prints it. */
export function blankData(): Record<string, unknown> {
    return editorData({
        id: null,
        code: '',
        name: '',
        type: '',
        template: '{\n  "@context": "https://schema.org"\n}',
        active: false,
        pageKinds: ['product'],
        conditions: '',
        storeIds: [0],
        origin: null,
        updatedAt: null,
        updatedBy: '',
    }, { isNew: true, usedIn: [] });
}

export interface EditorDocumentOptions {
    readonly canChange?: boolean;
    readonly canTest?: boolean;
}

/** The page's part of the island's document, as the server prints it around the editor's data. */
export function editorDocument(
    data: unknown,
    phrases: Readonly<Record<string, string>>,
    options: EditorDocumentOptions = {},
): Record<string, unknown> {
    return {
        shell: {
            module: 'Rich Snippets',
            page: 'Snippets',
            nav: [
                { code: 'overview', label: 'Overview', url: 'https://admin.test/admin/richsnippets/overview/index/', current: false },
                { code: 'snippets', label: 'Snippets', url: 'https://admin.test/admin/richsnippets/snippets/index/', current: true },
            ],
            storefront: 'https://shop.test/en/',
        },
        stores: STORES,
        phrases,
        urls: EDITOR_URLS,
        routes: EDITOR_ROUTES,
        formKey: 'form-key-1',
        permissions: { snippets: options.canChange ?? true, test: options.canTest ?? true, config: true },
        data,
    };
}
