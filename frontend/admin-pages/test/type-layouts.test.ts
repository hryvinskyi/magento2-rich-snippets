/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The workspace's type configuration in each layout the packages it is typed against may be installed in. Each case
 * lays out a project in a temporary directory — this workspace's tsconfig.json at the place the module would sit, the
 * expression builder's published declarations at the place its package would sit — and proves two things there: the
 * check script resolves the builder's module id to those declarations, and the compiler does too, typing a module
 * that imports it (a value of the wrong type is refused by name rather than the module going unfound).
 */
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFile, mkdir, mkdtemp, realpath, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { after, before, it } from 'node:test';
import { fileURLToPath } from 'node:url';

const WORKSPACE = fileURLToPath(new URL('..', import.meta.url));
const TSC = join(WORKSPACE, 'node_modules', 'typescript', 'bin', 'tsc');
const CHECK = new URL('../scripts/check.mjs', import.meta.url).href;
const BUILDER = 'Hryvinskyi_UiFormElements/js/expression-builder/mount';

/** Runs an export of the check script in its own process and answers what it returned, parsed. */
function fromCheck(expression: string): unknown {
    const run = spawnSync(process.execPath, [
        '--input-type=module',
        '-e',
        `import * as check from ${JSON.stringify(CHECK)}; process.stdout.write(JSON.stringify(${expression}));`,
    ], { encoding: 'utf8' });
    assert.equal(run.status, 0, run.stderr);
    return JSON.parse(run.stdout);
}

/** The declaration file the check script resolves the builder's module id to in a workspace; null for none. */
const resolvedDeclarations = (workspace?: string): unknown =>
    fromCheck(`check.resolvedDeclarations(${JSON.stringify(BUILDER)}${workspace === undefined ? '' : `, ${JSON.stringify(workspace)}`})`);

let scratch = '';

before(async () => {
    scratch = await realpath(await mkdtemp(join(tmpdir(), 'rich-snippets-type-layouts-')));
});

after(async () => {
    await rm(scratch, { recursive: true, force: true });
});

/** The builder's declarations as this checkout resolves them. */
function builderDeclarations(): string {
    const found = resolvedDeclarations();
    assert.ok(typeof found === 'string', 'this checkout resolves the builder declarations');
    return found;
}

/** Lays out a project: the workspace's tsconfig at `workspace`, the builder's declarations at `declarations`. */
async function layout(root: string, workspace: string, declarations: string): Promise<string> {
    const at = join(root, workspace);
    await mkdir(join(at, 'src'), { recursive: true });
    await copyFile(join(WORKSPACE, 'tsconfig.json'), join(at, 'tsconfig.json'));
    await symlink(join(WORKSPACE, 'node_modules'), join(at, 'node_modules'), 'dir');
    await mkdir(dirname(join(root, declarations)), { recursive: true });
    await copyFile(builderDeclarations(), join(root, declarations));
    await writeFile(join(at, 'src', 'probe.ts'), [
        `import type { MountExpressionBuilder } from '${BUILDER}';`,
        'export const probe: MountExpressionBuilder = 5;',
        '',
    ].join('\n'));
    return at;
}

/** The compiler's output over a laid-out workspace. */
function compile(workspace: string): string {
    const run = spawnSync(process.execPath, [TSC, '--noEmit', '-p', workspace], { cwd: workspace, encoding: 'utf8' });
    return `${run.stdout}${run.stderr}`;
}

const LAYOUTS: readonly { readonly name: string; readonly workspace: string; readonly declarations: string }[] = [
    {
        name: 'both checked out in app/code',
        workspace: 'app/code/Hryvinskyi/RichSnippets/frontend/admin-pages',
        declarations: 'app/code/Hryvinskyi/UiFormElements/types/mount.d.ts',
    },
    {
        name: 'both installed by Composer',
        workspace: 'vendor/hryvinskyi/magento2-rich-snippets/frontend/admin-pages',
        declarations: 'vendor/hryvinskyi/magento2-ui-form-elements/types/mount.d.ts',
    },
    {
        name: 'the module in app/code, the builder installed by Composer',
        workspace: 'app/code/Hryvinskyi/RichSnippets/frontend/admin-pages',
        declarations: 'vendor/hryvinskyi/magento2-ui-form-elements/types/mount.d.ts',
    },
];

it('checks the builder declarations as it checks the kit', () => {
    assert.deepEqual(fromCheck('check.PUBLISHED'), ['@hryvinskyi/admin-ui', BUILDER]);
});

for (const { name, workspace, declarations } of LAYOUTS) {
    it(`resolves the builder declarations with ${name}`, async () => {
        const root = await mkdtemp(join(scratch, 'layout-'));
        const at = await layout(root, workspace, declarations);

        assert.equal(resolvedDeclarations(at), join(root, declarations));
        const output = compile(at);
        assert.match(output, /TS2322/, output);
        assert.match(output, /MountExpressionBuilder/, output);
        assert.doesNotMatch(output, /TS2307/, output);
    });
}

it('resolves nothing when the builder package is in no layout', async () => {
    const root = await mkdtemp(join(scratch, 'none-'));
    const at = join(root, 'app/code/Hryvinskyi/RichSnippets/frontend/admin-pages');
    await mkdir(at, { recursive: true });
    await copyFile(join(WORKSPACE, 'tsconfig.json'), join(at, 'tsconfig.json'));

    assert.equal(resolvedDeclarations(at), null);
});
