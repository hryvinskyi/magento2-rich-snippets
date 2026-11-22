/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The build script run over the workspace's own output rules. Each case writes a list module into a temporary
 * directory that takes the workspace list's root, shared specifiers, refused libraries and per-page directories as
 * they are, and swaps in pages written for the case; the build script then builds that list into the same directory.
 * So what is proven is the workspace's configuration, not a copy of it: two pages may each import the admin UI kit
 * through its shared specifier without carrying it, one page's module may not be carried by another unless it lies in
 * a directory every page may carry, and a page that reaches the kit by path is refused.
 */
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readdir, readFile, realpath, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { after, before, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const BUILD = fileURLToPath(new URL('../scripts/build.mjs', import.meta.url));
const ENTRIES = new URL('../scripts/entries.mjs', import.meta.url).href;
const KIT_MODULE = fileURLToPath(import.meta.resolve('@hryvinskyi/admin-ui'));

let scratch = '';

before(async () => {
    scratch = await realpath(await mkdtemp(join(tmpdir(), 'rich-snippets-admin-pages-scripts-')));
});

after(async () => {
    await rm(scratch, { recursive: true, force: true });
});

/** A page's entry, written into the case's directory, importing the given modules by path. */
async function page(dir: string, name: string, imports: readonly string[]): Promise<string> {
    const file = join(dir, `${name}.ts`);
    const lines = imports.map((path, index) => `import * as m${index} from ${JSON.stringify(path)};`);
    const used = imports.map((_, index) => `m${index}`).join(', ');
    await writeFile(file, `${lines.join('\n')}\nexport const used = [${used}];\n`);
    return file;
}

/** Runs the build over the workspace's rules with the given pages: the build's exit code and its error output. */
async function buildPages(
    dir: string,
    pages: Readonly<Record<string, string>>,
): Promise<{ code: number | null; error: string }> {
    const list = join(dir, 'entries.mjs');
    const outputs = Object.entries(pages).map(([file, entry]) => ({ file, entry }));
    await writeFile(list, [
        `import workspace from ${JSON.stringify(ENTRIES)};`,
        'const external = workspace.outputs[0].external;',
        `const pages = ${JSON.stringify(outputs)};`,
        'export default { ...workspace, outputs: pages.map((output) => ({ ...output, external })) };',
        '',
    ].join('\n'));
    const run = spawnSync(process.execPath, [BUILD, '--out', join(dir, 'out'), '--entries', list], { encoding: 'utf8' });
    return { code: run.status, error: run.stderr };
}

it('builds two pages that each import the kit through its shared specifier, neither carrying it', async () => {
    const dir = await mkdtemp(join(scratch, 'shared-'));
    const one = await page(dir, 'one', ['@hryvinskyi/admin-ui']);
    const two = await page(dir, 'two', ['@hryvinskyi/admin-ui']);

    const run = await buildPages(dir, { 'one.js': one, 'two.js': two });

    assert.equal(run.code, 0, run.error);
    assert.deepEqual((await readdir(join(dir, 'out'))).sort(), ['one.js', 'two.js']);
    for (const file of ['one.js', 'two.js']) {
        const built = await readFile(join(dir, 'out', file), 'utf8');
        assert.match(built, /from"@hryvinskyi\/admin-ui"/, `${file} imports the kit by its specifier`);
    }
});

it('refuses a module of one page carried by another page as well', async () => {
    const dir = await mkdtemp(join(scratch, 'sideways-'));
    const own = join(dir, 'own-part.ts');
    await writeFile(own, 'export const part = 1;\n');
    const one = await page(dir, 'one', [own]);
    const two = await page(dir, 'two', [own]);

    const run = await buildPages(dir, { 'one.js': one, 'two.js': two });

    assert.notEqual(run.code, 0);
    assert.match(run.error, /own-part\.ts is bundled into both one\.js and two\.js/);
});

it('refuses a page that would carry the admin UI kit, imported by path instead of its shared specifier', async () => {
    const dir = await mkdtemp(join(scratch, 'kit-'));
    const one = await page(dir, 'one', [KIT_MODULE]);

    const run = await buildPages(dir, { 'one.js': one });

    assert.notEqual(run.code, 0);
    assert.match(run.error, /one\.js would bundle the admin UI kit/);
});

it('lets two pages each carry a module of a directory every page may carry, the shared adapters among them', async () => {
    const dir = await mkdtemp(join(scratch, 'each-'));
    const shared = fileURLToPath(new URL('../src/shared/file-download.ts', import.meta.url));
    const one = await page(dir, 'one', [shared]);
    const two = await page(dir, 'two', [shared]);

    const run = await buildPages(dir, { 'one.js': one, 'two.js': two });

    assert.equal(run.code, 0, run.error);
    for (const file of ['one.js', 'two.js']) {
        assert.match(await readFile(join(dir, 'out', file), 'utf8'), /Content-Disposition/, `${file} carries its own copy`);
    }
});
