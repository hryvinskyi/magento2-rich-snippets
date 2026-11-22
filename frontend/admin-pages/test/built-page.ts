/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One built page, run as the page runs it, for a test of that page's built output.
 *
 * Before the test's cases, the build script builds that one output, as the workspace's output list declares it, into
 * a temporary directory, and a resolve hook plays the page host's import map for the files the page loads: the UI
 * library's three entry points go to the kit's own copies, the kit to its built module. A case then places the
 * markup the admin page host prints (the kit's shared page fixture, `Test/_contract/island-page.html`), carrying the
 * page's island name and document in place of the fixture island's with the kit's part as printed, and imports the
 * built entry on it. The directory is removed after the cases.
 *
 * Only the built file shows that the bundle holds no copy of the UI library or the kit: a second copy would leave
 * the page's components without the kit's provider, and nothing would draw. Tests of the sources cannot see that,
 * because there every import resolves to the same file.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { mkdtemp, realpath, rm, writeFile } from 'node:fs/promises';
import { registerHooks } from 'node:module';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { after, before } from 'node:test';
import { fileURLToPath, pathToFileURL } from 'node:url';

const WORKSPACE = fileURLToPath(new URL('..', import.meta.url));
const FIXTURE_ISLAND = 'test-island';
const KIT_MODULE = fileURLToPath(import.meta.resolve('@hryvinskyi/admin-ui'));

export interface BuiltPageOptions {
    /** The island the page host prints for the page, as its entry starts it. */
    readonly island: string;
    /** The page's output file, as the workspace's output list names it. */
    readonly output: string;
}

export interface BuiltPage {
    /** The built entry's address; a query makes the next import a new module, so the entry starts again. */
    entry(query?: string): string;
    /** The island root the page host prints, with the page's document as its page part, appended to the body. */
    place(page: unknown): HTMLElement;
}

/** Builds the page's output for the cases of the calling test file, and hands them what they run it with. */
export function builtPage(options: BuiltPageOptions): BuiltPage {
    let built = '';
    let entry = '';

    before(async () => {
        // The real path: module URLs name it, and a temporary directory is often reached through a symbolic link.
        built = await realpath(await mkdtemp(join(tmpdir(), `${options.island}-built-`)));
        // The workspace's own output list, narrowed to the page's output.
        const list = join(built, 'entries.mjs');
        const declared = pathToFileURL(join(WORKSPACE, 'scripts/entries.mjs')).href;
        await writeFile(list, [
            `import entries from ${JSON.stringify(declared)};`,
            `export default { ...entries, outputs: entries.outputs.filter((output) => output.file === ${JSON.stringify(options.output)}) };`,
            '',
        ].join('\n'));
        const out = join(built, 'out');
        execFileSync(process.execPath, [join(WORKSPACE, 'scripts/build.mjs'), '--out', out, '--entries', list], { stdio: 'pipe' });
        // The browser runs every file as an ES module; so does Node, once the directory says so.
        await writeFile(join(out, 'package.json'), '{"type":"module"}');
        entry = pathToFileURL(join(out, options.output)).href;

        const kit = await realpath(KIT_MODULE);
        const kitFiles = dirname(kit);
        const importMap: Readonly<Record<string, string>> = {
            preact: pathToFileURL(join(kitFiles, 'vendor/preact.js')).href,
            'preact/hooks': pathToFileURL(join(kitFiles, 'vendor/preact-hooks.js')).href,
            'preact/jsx-runtime': pathToFileURL(join(kitFiles, 'vendor/preact-jsx-runtime.js')).href,
            // A module of its own: the sources the test imports have loaded the kit already, with the workspace's UI
            // library; the page's kit runs on the UI library the page host maps.
            '@hryvinskyi/admin-ui': `${pathToFileURL(kit).href}?page`,
        };
        const pageFiles = [out, kitFiles].map((directory) => `${pathToFileURL(directory).href}/`);
        registerHooks({
            resolve: (specifier, context, nextResolve) => {
                const url = importMap[specifier];
                const parent = context.parentURL ?? '';
                return url !== undefined && pageFiles.some((files) => parent.startsWith(files))
                    ? { url, shortCircuit: true, format: 'module' }
                    : nextResolve(specifier, context);
            },
        });
    });

    after(async () => {
        await rm(built, { recursive: true, force: true });
    });

    return {
        entry: (query = ''): string => (query === '' ? entry : `${entry}?${query}`),
        place(page: unknown): HTMLElement {
            // The kit is built into its module's view/adminhtml/web/js/admin-ui/.
            const file = fileURLToPath(new URL('../../../../../Test/_contract/island-page.html', pathToFileURL(KIT_MODULE)));
            const printed = document.createElement('template');
            printed.innerHTML = readFileSync(file, 'utf8');
            const root = printed.content.querySelector(`[data-role="${FIXTURE_ISLAND}"]`);
            const loading = root?.querySelector(`[data-role="${FIXTURE_ISLAND}-loading"]`);
            const script = root?.querySelector(`[data-role="${FIXTURE_ISLAND}-config"]`);
            if (!(root instanceof HTMLElement) || loading === null || loading === undefined || script === null || script === undefined) {
                throw new Error(`${file} holds no island root with its loading line and document`);
            }
            const island: unknown = JSON.parse(script.textContent);
            const kit: unknown = typeof island === 'object' && island !== null ? Reflect.get(island, 'kit') : undefined;
            root.setAttribute('data-role', options.island);
            loading.setAttribute('data-role', `${options.island}-loading`);
            script.setAttribute('data-role', `${options.island}-config`);
            script.textContent = JSON.stringify({ kit, page });
            const placed = document.importNode(root, true);
            document.body.append(placed);
            return placed;
        },
    };
}
