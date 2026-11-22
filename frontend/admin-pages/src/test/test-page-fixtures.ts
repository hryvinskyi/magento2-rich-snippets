/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's data as the server prints and answers it, for tests: the page's first data, page tests, searches,
 * check states, findings and the pages behind one, from the shared contract fixtures
 * (`Test/_contract/admin-pages/test-*.json`, which the server's tests check the same way), and the page document the
 * server prints around them. Each call returns a fresh copy.
 */
import { readFileSync } from 'node:fs';
import { isRecord } from '@hryvinskyi/admin-ui';
import { STORES } from './fixtures.ts';

const contract = (name: string): URL => new URL(`../../../../Test/_contract/admin-pages/test-${name}.json`, import.meta.url);

/** The keyed addresses of the screens the Test page links to, as an admin who may open them all gets them. */
export const TEST_ROUTES: Readonly<Record<string, string>> = Object.freeze({
    'hryvinskyi_richsnippets/snippets/edit': 'https://admin.test/admin/richsnippets/snippets/edit/key/e1/',
    'adminhtml/system_config/edit': 'https://admin.test/admin/admin/system_config/edit/key/c1/',
    'catalog/product/index': 'https://admin.test/admin/catalog/product/index/key/p1/',
    'catalog/product/edit': 'https://admin.test/admin/catalog/product/edit/key/p2/',
    'catalog/category/edit': 'https://admin.test/admin/catalog/category/edit/key/p3/',
    'cms/page/edit': 'https://admin.test/admin/cms/page/edit/key/p4/',
});

export const TEST_URLS: Readonly<Record<string, string>> = Object.freeze({
    search: 'https://admin.test/admin/richsnippets/test/search/key/t1/',
    run: 'https://admin.test/admin/richsnippets/test/run/key/t2/',
    checkStatus: 'https://admin.test/admin/richsnippets/test/checkStatus/key/t3/',
    checkRequest: 'https://admin.test/admin/richsnippets/test/checkRequest/key/t4/',
    findings: 'https://admin.test/admin/richsnippets/test/findings/key/t5/',
    findingItems: 'https://admin.test/admin/richsnippets/test/findingItems/key/t6/',
    findingsCsv: 'https://admin.test/admin/richsnippets/test/findingsCsv/key/t7/',
});

function entry(name: string, key: string): Record<string, unknown> {
    const value: unknown = JSON.parse(readFileSync(contract(name), 'utf8'));
    const found: unknown = isRecord(value) ? value[key] : undefined;
    if (!isRecord(found)) {
        throw new Error(`Test/_contract/admin-pages/test-${name}.json holds no "${key}"`);
    }
    return { ...found };
}

/**
 * The page tests of the contract: a page that meets everything, one with a problem, one with nothing, one two snippets
 * of one type are output on, one unread.
 */
export type RunCase = 'done' | 'problem' | 'nothing' | 'twice' | 'failed';

/** The check states of the contract. */
export type StatusCase = 'idle' | 'running' | 'waiting' | 'clean' | 'failed' | 'none';

/** The findings of the contract: four kinds of gap, a store view with a note only, and none at all. */
export type FindingsCase = 'findings' | 'clean' | 'none';

export const contractRun = (which: RunCase): Record<string, unknown> => entry('page-run', which);
export const contractSearch = (which: 'pages' | 'address'): Record<string, unknown> => entry('page-search', which);
export const contractStatus = (which: StatusCase): Record<string, unknown> => entry('catalog-status', which);
export const contractFindings = (which: FindingsCase): Record<string, unknown> => entry('catalog-findings', which);
export const contractItems = (which: 'first' | 'later' | 'all'): Record<string, unknown> => entry('catalog-finding-items', which);

/** The page's first data of the contract, opened in one mode, with fields changed. */
export const testData = (
    which: 'page' | 'catalog' = 'page',
    change: Readonly<Record<string, unknown>> = {},
): Record<string, unknown> => ({ ...entry('page-data', which), ...change });

export interface TestDocumentOptions {
    /** Whether the admin may run tests. */
    readonly canTest?: boolean;
    /** The screens left out, as for an admin whose role does not allow them. */
    readonly without?: readonly string[];
}

/** The page's part of the island's document, as the server prints it around the page's data. */
export function testDocument(
    data: unknown,
    phrases: Readonly<Record<string, string>>,
    options: TestDocumentOptions = {},
): Record<string, unknown> {
    const without = new Set(options.without ?? []);
    return {
        shell: {
            module: 'Rich Snippets',
            page: 'Test',
            nav: [
                { code: 'overview', label: 'Overview', url: 'https://admin.test/admin/richsnippets/overview/index/', current: false },
                { code: 'test', label: 'Test', url: 'https://admin.test/admin/richsnippets/test/index/', current: true },
            ],
            storefront: 'https://shop.test/en/',
        },
        stores: STORES,
        phrases,
        urls: TEST_URLS,
        routes: Object.fromEntries(Object.entries(TEST_ROUTES).filter(([route]) => !without.has(route))),
        formKey: 'form-key-1',
        permissions: { test: options.canTest ?? true },
        data,
    };
}
