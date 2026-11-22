/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
// @ts-check

/*
 * What the build produces from this workspace: one ES module per Rich Snippets admin page, `<page>.js`, the entry
 * the page's island names (`Hryvinskyi_RichSnippets::js/admin-pages/<page>.js`). A page is added by adding its entry
 * to PAGES.
 *
 * Every specifier the page host's import map resolves stays out of the bundles: the UI library's three entry points
 * and the admin UI kit. The sources of both are refused: an output that would carry either fails the build, naming
 * it, since the page would then hold two copies with state no one shares.
 *
 * What every page shares — the page document, links, requests, wording and frame — comes from the kit through its
 * specifier, so no source of this workspace is bundled into two outputs: one page's module imported by another
 * fails the build, unless it lies in a directory listed in BUNDLED_INTO_EACH, whose modules each page carries.
 */
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { KIT, packageRoot } from './packages.mjs';

/** The UI library's three entry points. */
export const UI_LIBRARY = ['preact', 'preact/hooks', 'preact/jsx-runtime'];

/** Every specifier the page's import map resolves for an island. */
export const SHARED = [...UI_LIBRARY, '@hryvinskyi/admin-ui'];

/**
 * The directories whose modules each page that imports them carries a copy of: small adapters several pages share
 * (a file download, the browser's file saving) that belong to no one page.
 */
export const BUNDLED_INTO_EACH = Object.freeze(['src/shared']);

/** Each page's output file and its entry module. */
export const PAGES = Object.freeze([
    { file: 'overview.js', entry: 'src/overview/main.tsx' },
    { file: 'snippets.js', entry: 'src/snippets/main.tsx' },
    { file: 'editor.js', entry: 'src/editor/main.tsx' },
    { file: 'library.js', entry: 'src/library/main.tsx' },
    { file: 'test.js', entry: 'src/test-page/main.tsx' },
]);

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

export default {
    root,
    tsconfig: 'tsconfig.json',
    shared: SHARED.map((specifier) => ({ specifier })),
    refused: [
        {
            label: 'the UI library',
            directory: dirname(createRequire(resolve(root, 'package.json')).resolve('preact/package.json')),
        },
        { label: 'the admin UI kit', directory: packageRoot(KIT) },
    ],
    bundledIntoEach: [...BUNDLED_INTO_EACH],
    outputs: PAGES.map((page) => ({ ...page, external: SHARED })),
};
