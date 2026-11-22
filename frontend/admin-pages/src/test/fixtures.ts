/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview's data as the server prints and answers it, for tests: the shared contract fixture
 * (`Test/_contract/admin-pages/overview-data.json`, which the server's presenter tests write the same way) with its
 * states — live with nothing to fix, live with things to fix, one store view, nothing added, added and all off — and
 * the page document the server prints around it. Each call returns a fresh copy.
 */
import { readFileSync } from 'node:fs';

const CONTRACT = new URL('../../../../Test/_contract/admin-pages/overview-data.json', import.meta.url);

export type OverviewState = 'healthy' | 'attention' | 'deutsch' | 'none' | 'off';

/** The keyed addresses of the screens the Overview links to, as an admin who may open them all gets them. */
export const ROUTES: Readonly<Record<string, string>> = Object.freeze({
    'hryvinskyi_richsnippets/test/index': 'https://admin.test/admin/richsnippets/test/index/key/t1/',
    'hryvinskyi_richsnippets/snippets/index': 'https://admin.test/admin/richsnippets/snippets/index/key/s1/',
    'hryvinskyi_richsnippets/snippets/new': 'https://admin.test/admin/richsnippets/snippets/new/key/n1/',
    'hryvinskyi_richsnippets/library/index': 'https://admin.test/admin/richsnippets/library/index/key/l1/',
    'adminhtml/system_config/edit': 'https://admin.test/admin/admin/system_config/edit/key/c1/',
});

export const DATA_URL = 'https://admin.test/admin/richsnippets/overview/data/key/d1/';

export const STORES = Object.freeze([
    { id: 1, name: 'English', home: 'https://shop.test/en/' },
    { id: 2, name: 'Deutsch', home: 'https://shop.test/de/' },
    { id: 3, name: 'Trade', home: 'https://shop.test/trade/' },
]);

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** One state of the contract fixture, as the server prints it into the page and answers it. */
export function stateData(state: OverviewState): Record<string, unknown> {
    const value: unknown = JSON.parse(readFileSync(CONTRACT, 'utf8'));
    const data: unknown = isObject(value) ? value[state] : undefined;
    if (!isObject(data)) {
        throw new Error(`Test/_contract/admin-pages/overview-data.json holds no state "${state}"`);
    }
    return data;
}

export interface DocumentOptions {
    /** The screens left out, as for an admin whose role does not allow them. */
    readonly without?: readonly string[];
}

/** The page's part of the island's document, as the server prints it around a state's data. */
export function pageDocument(
    data: unknown,
    phrases: Readonly<Record<string, string>>,
    options: DocumentOptions = {},
): Record<string, unknown> {
    const without = new Set(options.without ?? []);
    const routes = Object.fromEntries(Object.entries(ROUTES).filter(([route]) => !without.has(route)));
    return {
        shell: {
            module: 'Rich Snippets',
            page: 'Overview',
            nav: [
                { code: 'overview', label: 'Overview', url: 'https://admin.test/admin/richsnippets/overview/index/', current: true },
                { code: 'snippets', label: 'Snippets', url: ROUTES['hryvinskyi_richsnippets/snippets/index'], current: false },
                { code: 'library', label: 'Library', url: ROUTES['hryvinskyi_richsnippets/library/index'], current: false },
                ...(without.has('hryvinskyi_richsnippets/test/index') ? [] : [
                    { code: 'test', label: 'Test', url: ROUTES['hryvinskyi_richsnippets/test/index'], current: false },
                ]),
                { code: 'settings', label: 'Settings', url: ROUTES['adminhtml/system_config/edit'], current: false },
            ],
            storefront: 'https://shop.test/en/',
        },
        stores: STORES,
        phrases,
        urls: { data: DATA_URL },
        routes,
        formKey: 'form-key-1',
        permissions: {
            snippets: !without.has('hryvinskyi_richsnippets/snippets/new'),
            test: !without.has('hryvinskyi_richsnippets/test/index'),
            config: !without.has('adminhtml/system_config/edit'),
        },
        data,
    };
}
