/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page's data as the server prints and answers it, for tests: the libraries, suggested answers and add
 * answers of the shared contract fixtures (`Test/_contract/admin-pages/library-*.json`, which the server's presenter
 * tests write the same way), and the page document the server prints around them. Each call returns a fresh copy.
 */
import { readFileSync } from 'node:fs';
import { isRecord } from '@hryvinskyi/admin-ui';
import { STORES } from './fixtures.ts';

const contract = (name: string): URL => new URL(`../../../../Test/_contract/admin-pages/library-${name}.json`, import.meta.url);

/** The keyed addresses of the screens the Library links to, as an admin who may open them all gets them. */
export const LIBRARY_ROUTES: Readonly<Record<string, string>> = Object.freeze({
    'hryvinskyi_richsnippets/snippets/new': 'https://admin.test/admin/richsnippets/snippets/new/key/n1/',
    'hryvinskyi_richsnippets/snippets/edit': 'https://admin.test/admin/richsnippets/snippets/edit/key/e1/',
    'hryvinskyi_richsnippets/test/index': 'https://admin.test/admin/richsnippets/test/index/key/t1/',
});

export const LIBRARY_URLS: Readonly<Record<string, string>> = Object.freeze({
    list: 'https://admin.test/admin/richsnippets/library/list/key/b1/',
    suggestions: 'https://admin.test/admin/richsnippets/library/suggestions/key/b2/',
    add: 'https://admin.test/admin/richsnippets/library/add/key/b3/',
    addRecommended: 'https://admin.test/admin/richsnippets/library/addRecommended/key/b4/',
});

/** The libraries of the contract: `lib`, a shop that has nine templates; `first`, a shop that has none. */
export type LibraryCase = 'lib' | 'first';

/** The templates whose answers the contract holds. */
export type SuggestionsCase = 'product' | 'organization' | 'breadcrumbs';

/**
 * The add answers of the contract: a template with the block it uses; a second copy; a building block on its own;
 * the recommended set; and a refused add.
 */
export type AddCase = 'added' | 'again' | 'block' | 'recommended' | 'refused';

function entry(name: string, key: string): Record<string, unknown> {
    const value: unknown = JSON.parse(readFileSync(contract(name), 'utf8'));
    const found: unknown = isRecord(value) ? value[key] : undefined;
    if (!isRecord(found)) {
        throw new Error(`Test/_contract/admin-pages/library-${name}.json holds no "${key}"`);
    }
    return found;
}

/** A library of the contract, as the list endpoint answers it. */
export const contractLibrary = (which: LibraryCase = 'lib'): Record<string, unknown> => entry('list', which);

/** A template's questions and the answers this shop is offered, as the suggestions endpoint answers them. */
export const contractSuggestions = (template: SuggestionsCase): Record<string, unknown> => entry('suggestions', template);

/** An add answer of the contract, as the add endpoints answer it. */
export const contractAdd = (which: AddCase): Record<string, unknown> => entry('add', which);

function templatesOf(library: Record<string, unknown>): Record<string, unknown>[] {
    const templates = library['templates'];
    return Array.isArray(templates) ? templates.filter(isRecord) : [];
}

/** A library with one template changed. */
export function libraryChanging(
    code: string,
    change: Readonly<Record<string, unknown>>,
    from: Record<string, unknown> = contractLibrary(),
): Record<string, unknown> {
    return { ...from, templates: templatesOf(from).map((template) => (template['code'] === code ? { ...template, ...change } : template)) };
}

/** The page's first data: a library, and the template whose drawer opens once the page is drawn. */
export const libraryData = (library: Record<string, unknown> = contractLibrary(), open: string | null = null): Record<string, unknown> =>
    ({ ...library, open, unavailable: false });

/** The page's first data when the server could not read the library: no template, and the library unavailable. */
export const unavailableData = (): Record<string, unknown> =>
    ({ groups: [], recommended: [], templates: [], open: null, unavailable: true });

export interface LibraryDocumentOptions {
    /** Whether the admin may add templates. */
    readonly canChange?: boolean;
    /** The screens left out, as for an admin whose role does not allow them. */
    readonly without?: readonly string[];
}

/** The page's part of the island's document, as the server prints it around the page's data. */
export function libraryDocument(
    data: unknown,
    phrases: Readonly<Record<string, string>>,
    options: LibraryDocumentOptions = {},
): Record<string, unknown> {
    const canChange = options.canChange ?? true;
    const without = new Set([
        ...(options.without ?? []),
        ...(canChange ? [] : ['hryvinskyi_richsnippets/snippets/new']),
    ]);
    return {
        shell: {
            module: 'Rich Snippets',
            page: 'Library',
            nav: [
                { code: 'overview', label: 'Overview', url: 'https://admin.test/admin/richsnippets/overview/index/', current: false },
                { code: 'library', label: 'Library', url: 'https://admin.test/admin/richsnippets/library/index/', current: true },
            ],
            storefront: 'https://shop.test/en/',
        },
        stores: STORES,
        phrases,
        urls: LIBRARY_URLS,
        routes: Object.fromEntries(Object.entries(LIBRARY_ROUTES).filter(([route]) => !without.has(route))),
        formKey: 'form-key-1',
        permissions: { snippets: canChange },
        data,
    };
}
