/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's data as the server prints and answers it, for tests: the list of the shared contract fixture
 * (`Test/_contract/admin-pages/snippets-data.json`, which the server's presenter test writes the same way), the kinds
 * of page the server names, an import preview of the shared contract fixture, and the page document the server
 * prints around them. Each call returns a fresh copy.
 */
import { readFileSync } from 'node:fs';
import { STORES } from './fixtures.ts';

const CONTRACT = new URL('../../../../Test/_contract/admin-pages/snippets-data.json', import.meta.url);
const IMPORT_PREVIEW = new URL('../../../../Test/_contract/admin-pages/snippets-import-preview.json', import.meta.url);

/** The keyed addresses of the screens the Snippets page links to, as an admin who may open them all gets them. */
export const SNIPPETS_ROUTES: Readonly<Record<string, string>> = Object.freeze({
    'hryvinskyi_richsnippets/snippets/new': 'https://admin.test/admin/richsnippets/snippets/new/key/n1/',
    'hryvinskyi_richsnippets/snippets/edit': 'https://admin.test/admin/richsnippets/snippets/edit/key/e1/',
    'hryvinskyi_richsnippets/library/index': 'https://admin.test/admin/richsnippets/library/index/key/l1/',
});

export const SNIPPETS_URLS: Readonly<Record<string, string>> = Object.freeze({
    list: 'https://admin.test/admin/richsnippets/snippets/list/key/a1/',
    setActive: 'https://admin.test/admin/richsnippets/snippets/setActive/key/a2/',
    duplicate: 'https://admin.test/admin/richsnippets/snippets/duplicate/key/a3/',
    delete: 'https://admin.test/admin/richsnippets/snippets/delete/key/a4/',
    export: 'https://admin.test/admin/richsnippets/snippets/export/key/a5/',
    importPreview: 'https://admin.test/admin/richsnippets/snippets/importPreview/key/a6/',
    importCommit: 'https://admin.test/admin/richsnippets/snippets/importCommit/key/a7/',
});

/** The kinds of page as the server names them, in its order. */
export const KINDS = Object.freeze([
    { code: 'home', label: 'Home page', phrase: 'the home page', icon: 'home' },
    { code: 'product', label: 'Product pages', phrase: 'product pages', icon: 'inventory_2' },
    { code: 'category', label: 'Category pages', phrase: 'category pages', icon: 'category' },
    { code: 'cms', label: 'CMS pages', phrase: 'CMS pages', icon: 'article' },
    { code: 'other', label: 'Every other page', phrase: 'every other page', icon: 'public' },
]);

export const OUTPUT_LINE = 'A snippet that is on writes its block into the page head.';

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function rowsOf(list: Record<string, unknown>): Record<string, unknown>[] {
    const rows = list['rows'];
    return Array.isArray(rows) ? rows.filter(isObject) : [];
}

/** The contract's list, as the list endpoint answers it. */
export function contractList(): Record<string, unknown> {
    const value: unknown = JSON.parse(readFileSync(CONTRACT, 'utf8'));
    const list: unknown = isObject(value) ? value['data'] : undefined;
    if (!isObject(list)) {
        throw new Error('Test/_contract/admin-pages/snippets-data.json holds no "data"');
    }
    return list;
}

/** A shop with no snippet, as the server writes it: empty objects come as empty lists. */
export const emptyList = (): Record<string, unknown> => ({
    rows: [],
    counts: { all: 0, attention: 0, kinds: [], blocks: 0, on: 0, off: 0, flags: { bad: 0, warn: 0, note: 0 } },
});

/** The contract's list with only the rows `keep` lets through, counted again. */
export function listWith(keep: (row: Record<string, unknown>) => boolean, from = contractList()): Record<string, unknown> {
    const rows = rowsOf(from).filter(keep);
    const kinds: Record<string, number> = {};
    for (const row of rows) {
        const codes = Array.isArray(row['pageKinds']) ? row['pageKinds'] : [];
        for (const code of codes) {
            if (typeof code === 'string' && row['isBlock'] !== true) {
                kinds[code] = (kinds[code] ?? 0) + 1;
            }
        }
    }
    const flagged = (name: string): number => rows.filter((row) => isObject(row['flags']) && row['flags'][name] !== null).length;
    const pages = rows.filter((row) => row['isBlock'] !== true);
    return {
        rows,
        counts: {
            all: rows.length,
            attention: rows.filter((row) => isObject(row['flags']) && (row['flags']['bad'] !== null || row['flags']['warn'] !== null)).length,
            kinds,
            blocks: rows.length - pages.length,
            on: pages.filter((row) => row['active'] === true).length,
            off: pages.filter((row) => row['active'] !== true).length,
            flags: { bad: flagged('bad'), warn: flagged('warn'), note: flagged('note') },
        },
    };
}

/** The contract's list with one row changed. */
export function listChanging(code: string, change: Readonly<Record<string, unknown>>): Record<string, unknown> {
    const list = contractList();
    return { ...list, rows: rowsOf(list).map((row) => (row['code'] === code ? { ...row, ...change } : row)) };
}

/** The page's first data around a list. */
export const snippetsData = (list: unknown = contractList()): Record<string, unknown> =>
    ({ kinds: KINDS, outputLine: OUTPUT_LINE, list });

/**
 * An import preview with a snippet of each standing — new, changed here and in the file, identical, a block — as the
 * preview endpoint answers it (`Test/_contract/admin-pages/snippets-import-preview.json`, which the server's test of
 * the preview writes the same way).
 */
export function importPreview(): Record<string, unknown> {
    const value: unknown = JSON.parse(readFileSync(IMPORT_PREVIEW, 'utf8'));
    const preview: unknown = isObject(value) ? value['preview'] : undefined;
    if (!isObject(preview)) {
        throw new Error('Test/_contract/admin-pages/snippets-import-preview.json holds no "preview"');
    }
    return preview;
}

export interface SnippetsDocumentOptions {
    /** Whether the admin may switch, copy, delete, export and import snippets. */
    readonly canChange?: boolean;
    /** The screens left out, as for an admin whose role does not allow them. */
    readonly without?: readonly string[];
}

/** The page's part of the island's document, as the server prints it around the page's data. */
export function snippetsDocument(
    data: unknown,
    phrases: Readonly<Record<string, string>>,
    options: SnippetsDocumentOptions = {},
): Record<string, unknown> {
    const canChange = options.canChange ?? true;
    const without = new Set([
        ...(options.without ?? []),
        ...(canChange ? [] : ['hryvinskyi_richsnippets/snippets/new']),
    ]);
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
        urls: SNIPPETS_URLS,
        routes: Object.fromEntries(Object.entries(SNIPPETS_ROUTES).filter(([route]) => !without.has(route))),
        formKey: 'form-key-1',
        permissions: { snippets: canChange },
        data,
    };
}
