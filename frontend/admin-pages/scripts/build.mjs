/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Builds the admin pages' browser files into the module's view directory (or `--out <dir>`), from the outputs that
 * scripts/entries.mjs lists (or the list module `--entries <file>` names).
 *
 * Every output is an ES module, minified, built for ES2022. Shared specifiers are external by exact match only:
 * esbuild's own `external` option would also externalize every subpath (`preact` would swallow `preact/hooks`),
 * leaving a re-export of the subpath empty.
 *
 * Every cross-file import is a bare specifier the page's import map resolves: a relative one would break once the
 * files are published under minified names, so an output that imports anything else fails the build. No output may
 * carry a source of a `refused` directory — a library the page loads from elsewhere: the build fails naming the
 * output and the source. No source file may end up in two outputs either, unless it lies under a directory the list
 * names in `bundledIntoEach`; why a directory is listed there is the list's to say. The output is deterministic (no
 * metafile written, no timestamps), so verify-build can compare it byte for byte. With no output listed, nothing is
 * written.
 */
import { build } from 'esbuild';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, isAbsolute, join, relative, resolve } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';

const WORKSPACE = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DEFAULT_OUT = resolve(WORKSPACE, '../../view/adminhtml/web/js/admin-pages');
const DEFAULT_ENTRIES = join(WORKSPACE, 'scripts/entries.mjs');

/**
 * @typedef {{ specifier: string, directory?: string }} Shared
 * @typedef {{ file: string, entry?: string, reexport?: string, external: string[] }} Output
 * @typedef {{ label: string, directory: string }} Refused
 * @typedef {{
 *     root: string,
 *     tsconfig?: string,
 *     shared: Shared[],
 *     refused?: Refused[],
 *     bundledIntoEach?: string[],
 *     outputs: Output[],
 * }} Entries
 */

/**
 * Reads the list module and refuses a list it cannot build from.
 *
 * @param {string} file
 * @returns {Promise<Entries>}
 */
async function loadEntries(file) {
    const { default: entries } = await import(pathToFileURL(resolve(file)).href);
    if (typeof entries?.root !== 'string' || !Array.isArray(entries.shared) || !Array.isArray(entries.outputs)) {
        throw new Error(`${file}: expected a default export {root, shared: [], outputs: []}`);
    }
    const refused = entries.refused ?? [];
    const wrong = (item) => typeof item?.label !== 'string' || typeof item?.directory !== 'string';
    if (!Array.isArray(refused) || refused.some(wrong)) {
        throw new Error(`${file}: refused, when given, lists {label, directory}`);
    }
    const each = entries.bundledIntoEach ?? [];
    if (!Array.isArray(each) || each.some((directory) => typeof directory !== 'string' || directory === '')) {
        throw new Error(`${file}: bundledIntoEach, when given, lists directories`);
    }
    for (const output of entries.outputs) {
        const sources = [output.entry, output.reexport].filter((source) => typeof source === 'string');
        if (typeof output.file !== 'string' || sources.length !== 1 || !Array.isArray(output.external)) {
            throw new Error(`${file}: every output needs a file, exactly one of entry or reexport, and an external list`);
        }
    }
    return entries;
}

/**
 * Keeps exactly the listed specifiers out of the bundle, and a relative import that reaches into a shared
 * specifier's directory from outside it, as written.
 *
 * @param {string[]} specifiers
 * @param {string[]} directories absolute
 * @returns {import('esbuild').Plugin}
 */
const externals = (specifiers, directories) => ({
    name: 'plugin-externals',
    setup(builder) {
        const inside = (path, directory) => {
            const rest = relative(directory, path);
            return rest !== '' && !rest.startsWith('..') && !isAbsolute(rest);
        };
        builder.onResolve({ filter: /.*/ }, (args) => {
            if (specifiers.includes(args.path)) {
                return { path: args.path, external: true };
            }
            if (args.kind === 'entry-point' || !args.path.startsWith('.') || args.resolveDir === '') {
                return undefined;
            }
            const target = resolve(args.resolveDir, args.path);
            const crosses = directories.some((directory) => inside(target, directory) && !inside(args.importer, directory));
            return crosses ? { path: args.path, external: true } : undefined;
        });
    },
});

const isBare = (specifier) => !specifier.startsWith('.') && !specifier.startsWith('/') && !/^[a-z]+:/i.test(specifier);

/**
 * The esbuild input of one output.
 *
 * @param {Entries} entries
 * @param {Output} output
 */
const inputOf = (entries, output) => (output.entry !== undefined
    ? { entryPoints: [output.entry] }
    : { stdin: { contents: `export * from '${output.reexport}';`, resolveDir: entries.root, loader: 'js', sourcefile: `${output.reexport}.js` } });

/**
 * Builds one output in memory and refuses it when it imports anything but a bare specifier.
 *
 * @param {Entries} entries
 * @param {string} outDir
 * @param {Output} output
 * @returns {Promise<{contents: Uint8Array, inputs: string[]}>}
 */
async function buildOne(entries, outDir, output) {
    const directories = entries.shared
        .filter((shared) => typeof shared.directory === 'string')
        .map((shared) => resolve(entries.root, shared.directory));
    const result = await build({
        ...inputOf(entries, output),
        absWorkingDir: entries.root,
        ...(entries.tsconfig === undefined ? {} : { tsconfig: join(entries.root, entries.tsconfig) }),
        outfile: join(outDir, output.file),
        plugins: [externals(output.external, directories)],
        bundle: true,
        format: 'esm',
        platform: 'browser',
        target: 'es2022',
        minify: true,
        splitting: false,
        legalComments: 'eof',
        charset: 'utf8',
        metafile: true,
        write: false,
        logLevel: 'warning',
    });
    for (const meta of Object.values(result.metafile.outputs)) {
        const refused = meta.imports.filter((item) => !item.external || !isBare(item.path));
        if (refused.length > 0) {
            throw new Error(`${output.file} imports ${refused.map((item) => item.path).join(', ')}; only bare specifiers are allowed`);
        }
    }
    const [file] = result.outputFiles;
    if (file === undefined || result.outputFiles.length !== 1) {
        throw new Error(`${output.file}: expected exactly one output file`);
    }
    return { contents: file.contents, inputs: Object.keys(result.metafile.inputs) };
}

/**
 * Refuses an output that bundles a source of a refused directory.
 *
 * @param {Entries} entries
 * @param {string} file the output
 * @param {string[]} sources the source files it bundles, relative to the entries' root
 */
function refuseLibraries(entries, file, sources) {
    const inside = (path, directory) => {
        const rest = relative(directory, path);
        return rest !== '' && !rest.startsWith('..') && !isAbsolute(rest);
    };
    for (const source of sources) {
        const path = resolve(entries.root, source);
        const library = (entries.refused ?? []).find((item) => inside(path, resolve(entries.root, item.directory)));
        if (library !== undefined) {
            throw new Error(`${file} would bundle ${library.label} (${source}): the page loads it from its own file; `
                + 'import it through its shared specifier');
        }
    }
}

/**
 * Refuses a source file bundled into two outputs, unless it lies under a directory of the list's `bundledIntoEach`.
 *
 * @param {Entries} entries
 * @param {Map<string, string[]>} inputs output file → the source files it bundles
 */
function refuseTwiceBundled(entries, inputs) {
    const inside = (path, directory) => {
        const rest = relative(directory, path);
        return rest !== '' && !rest.startsWith('..') && !isAbsolute(rest);
    };
    const each = (entries.bundledIntoEach ?? []).map((directory) => resolve(entries.root, directory));
    const owners = new Map();
    for (const [file, sources] of inputs) {
        for (const source of sources) {
            if (each.some((directory) => inside(resolve(entries.root, source), directory))) {
                continue;
            }
            const owner = owners.get(source);
            if (owner !== undefined) {
                throw new Error(`${source} is bundled into both ${owner} and ${file}: import it through its shared specifier`);
            }
            owners.set(source, file);
        }
    }
}

const { values } = parseArgs({ options: { out: { type: 'string' }, entries: { type: 'string' } } });
const outDir = resolve(values.out ?? DEFAULT_OUT);
const entries = await loadEntries(values.entries ?? DEFAULT_ENTRIES);

const built = new Map();
for (const output of entries.outputs) {
    const result = await buildOne(entries, outDir, output);
    if (output.entry !== undefined) {
        refuseLibraries(entries, output.file, result.inputs);
    }
    built.set(output.file, result);
}
refuseTwiceBundled(entries, new Map([...built].map(([file, result]) => [file, result.inputs])));
for (const [file, result] of built) {
    const target = join(outDir, file);
    await mkdir(dirname(target), { recursive: true });
    await writeFile(target, result.contents);
}
console.log(`Built ${built.size} files into ${outDir}`);
