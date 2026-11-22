/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
// @ts-check

/*
 * The type check, against the published declarations of the admin UI kit.
 *
 *   node scripts/check.mjs                 the UI library's version against the kit's, then every project here
 *   node scripts/check.mjs <tsconfig> ...  the projects named
 *
 * The workspace maps the bare specifiers of the packages it is typed against in its own tsconfig.json — the kit's, and
 * the expression builder's module id (`hryvinskyi/magento2-ui-form-elements`) — one candidate location per layout a
 * package may be installed in: checked out beside this module in `app/code`, installed beside it by Composer, or
 * installed in the project's `vendor/` while this module sits in `app/code`. Before anything is compiled, each mapped
 * specifier must resolve to a declaration file that exists; otherwise the check stops with a message naming the
 * specifier and every place tried, rather than the compiler's "cannot find module". The pure modules (each `src/<part>/domain/`) are checked on their own, without the DOM:
 * their project maps the kit's specifier to the kit's pure declarations (`types/pure.d.ts`) alone, so a pure module
 * that reached for a component or the network would not compile.
 *
 * The page loads one UI library, the kit's, so this workspace builds and tests against the same version: the check
 * fails when the kit pins another one. Exits non-zero when a package is missing, the versions differ, or a project
 * does not check clean.
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { kitManifest } from './packages.mjs';

const WORKSPACE = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const TSC = join(WORKSPACE, 'node_modules', 'typescript', 'bin', 'tsc');

/** Every project of this workspace, in the order they are checked. */
export const PROJECTS = Object.freeze(['tsconfig.json', 'tsconfig.domain.json', 'tsconfig.scripts.json']);

/** The specifiers the workspace's tsconfig maps to the published declarations of the packages it is typed against. */
export const PUBLISHED = Object.freeze([
    '@hryvinskyi/admin-ui',
    'Hryvinskyi_UiFormElements/js/expression-builder/mount',
]);

/**
 * A parsed JSON object, or an empty one for anything else
 *
 * @param {unknown} value
 * @returns {Record<string, unknown>}
 */
function objectOf(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value) ? { ...value } : {};
}

/**
 * The places a workspace's tsconfig maps a specifier to, made absolute
 *
 * @param {string} specifier
 * @param {string} [workspace] the directory holding the tsconfig.json; this workspace when left out
 * @returns {string[]}
 */
export function mappedPlaces(specifier, workspace = WORKSPACE) {
    /** @type {unknown} */
    const parsed = JSON.parse(readFileSync(join(workspace, 'tsconfig.json'), 'utf8'));
    const paths = objectOf(objectOf(objectOf(parsed)['compilerOptions'])['paths']);
    const targets = paths[specifier];

    return (Array.isArray(targets) ? targets : [])
        .filter(function (/** @type {unknown} */ target) {
            return typeof target === 'string';
        })
        .map(function (/** @type {string} */ target) {
            return resolve(workspace, target);
        });
}

/**
 * The declaration file a workspace's tsconfig resolves a specifier to: the first mapped place that exists; null when
 * none does
 *
 * @param {string} specifier
 * @param {string} [workspace]
 * @returns {string | null}
 */
export function resolvedDeclarations(specifier, workspace = WORKSPACE) {
    return mappedPlaces(specifier, workspace).find(function (place) {
        return existsSync(place);
    }) ?? null;
}

/**
 * Whether the published specifier resolves to declarations that exist; says so when it does not
 *
 * @returns {boolean}
 */
function publishedTypesResolve() {
    let resolved = true;
    for (const specifier of PUBLISHED) {
        const found = resolvedDeclarations(specifier);
        if (found === null) {
            process.stderr.write(`${specifier} resolves to no declaration file: install the package that publishes `
                + `it, or check it out beside this module. Tried:\n  ${mappedPlaces(specifier).join('\n  ')}\n`);
            resolved = false;
        } else {
            process.stdout.write(`${specifier}: ${found}\n`);
        }
    }

    return resolved;
}

/**
 * The version a manifest pins the UI library at
 *
 * @param {string} manifest
 * @returns {string}
 */
function uiLibraryVersion(manifest) {
    /** @type {unknown} */
    const parsed = JSON.parse(readFileSync(manifest, 'utf8'));
    const config = objectOf(parsed);
    const pinned = objectOf(config['devDependencies'])['preact'] ?? objectOf(config['dependencies'])['preact'];

    return typeof pinned === 'string' ? pinned : '(none)';
}

/**
 * Whether this workspace pins the UI library at the kit's version
 *
 * @returns {boolean}
 */
function sameUiLibrary() {
    const own = uiLibraryVersion(join(WORKSPACE, 'package.json'));
    const kits = uiLibraryVersion(kitManifest());
    if (own === kits) {
        process.stdout.write(`The UI library is ${own}, as the kit's.\n`);

        return true;
    }
    process.stderr.write(`This workspace pins the UI library (preact) at ${own}, the kit at ${kits} `
        + `(${kitManifest()}); the page loads the kit's, so pin the same version here.\n`);

    return false;
}

/**
 * Checks one project
 *
 * @param {string} project an absolute path
 * @returns {boolean} whether it checked clean
 */
function checkProject(project) {
    const run = spawnSync(process.execPath, [TSC, '--noEmit', '-p', project], { cwd: WORKSPACE, stdio: 'inherit' });
    process.stdout.write(`${project}: ${run.status === 0 ? 'clean' : 'failed'}\n`);

    return run.status === 0;
}

/**
 * @param {string[]} named the projects asked for; every project of this workspace when none
 * @returns {number} the exit code
 */
function main(named) {
    const all = named.length === 0;
    try {
        let clean = publishedTypesResolve();
        if (!clean) {
            return 1;
        }
        clean = (all ? sameUiLibrary() : true) && clean;
        const projects = (all ? [...PROJECTS] : named).map(function (project) {
            return resolve(all ? WORKSPACE : process.cwd(), project);
        });
        for (const project of projects) {
            clean = checkProject(project) && clean;
        }

        return clean ? 0 : 1;
    } catch (error) {
        process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`);

        return 1;
    }
}

if (process.argv[1] !== undefined && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    process.exitCode = main(process.argv.slice(2));
}
