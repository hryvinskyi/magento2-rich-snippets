/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
// @ts-check

/*
 * Resolves the shared specifiers in every test process the way an island page's import map does, for every importer
 * alike:
 *
 * - `@hryvinskyi/admin-ui` is the admin UI kit's built module (scripts/packages.mjs finds it in either layout), as the
 *   page loads it;
 * - `preact`, `preact/hooks` and `preact/jsx-runtime` are the ES modules of this workspace's own copy, whoever
 *   imports them — the kit, the sources, the test libraries — so a test process holds one UI library, as a page
 *   does, and the kit's components read the provider the sources give.
 *
 * Registered before any test file is loaded (`node --import`); importing it registers the hooks and does nothing else.
 */
import { readFileSync } from 'node:fs';
import { createRequire, registerHooks } from 'node:module';
import { dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { KIT, packageModule } from '../scripts/packages.mjs';

/** This workspace's copy of the UI library. */
const UI_LIBRARY_ROOT = dirname(createRequire(import.meta.url).resolve('preact/package.json'));

/**
 * The ES module of one of the UI library's entry points, as its manifest's `import` condition names it
 *
 * @param {string} subpath `.`, `./hooks` or `./jsx-runtime`
 * @returns {string}
 */
function uiLibraryModule(subpath) {
    /** @type {unknown} */
    const manifest = JSON.parse(readFileSync(join(UI_LIBRARY_ROOT, 'package.json'), 'utf8'));
    const exportsMap = typeof manifest === 'object' && manifest !== null ? Reflect.get(manifest, 'exports') : null;
    const entry = typeof exportsMap === 'object' && exportsMap !== null ? Reflect.get(exportsMap, subpath) : null;
    const file = typeof entry === 'object' && entry !== null ? Reflect.get(entry, 'import') : null;
    if (typeof file !== 'string') {
        throw new Error(`The UI library declares no ES module for ${subpath}`);
    }

    return pathToFileURL(join(UI_LIBRARY_ROOT, file)).href;
}

/** The UI library's three entry points, each to its ES module in this workspace's copy. */
const UI_LIBRARY = new Map([
    ['preact', uiLibraryModule('.')],
    ['preact/hooks', uiLibraryModule('./hooks')],
    ['preact/jsx-runtime', uiLibraryModule('./jsx-runtime')],
]);

/** The page's built modules, by the bare specifier the import map gives each. */
const PAGE_MODULES = new Map([
    ['@hryvinskyi/admin-ui', pathToFileURL(packageModule(KIT)).href],
]);

registerHooks({
    resolve(specifier, context, nextResolve) {
        const module = PAGE_MODULES.get(specifier);
        if (module !== undefined) {
            return { url: module, format: 'module', shortCircuit: true };
        }
        // The workspace's tsconfig maps these to the library's declarations for the type check; a loader that reads
        // the tsconfig would load those instead of the library.
        const library = UI_LIBRARY.get(specifier);
        if (library !== undefined) {
            return { url: library, format: 'module', shortCircuit: true };
        }

        return nextResolve(specifier, context);
    },
});
