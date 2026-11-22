/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
// @ts-check

/*
 * Finds the admin UI kit (`hryvinskyi/magento2-admin-ui`, which publishes `@hryvinskyi/admin-ui`) in either layout it
 * is found in: checked out beside this module (`app/code/Hryvinskyi/AdminUi`), or installed by Composer
 * (`vendor/hryvinskyi/magento2-admin-ui` in this module's directory or any directory above it).
 *
 * It names the kit's built module the page loads (and the tests run), the directory the build must never bundle a
 * source from, and the manifest that pins the UI library's version.
 */
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/** This module's directory: the workspace sits in `frontend/admin-pages/`. */
export const MODULE_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');

/**
 * @typedef {{ label: string, directory: string, composer: string, module: string, types: string }} Package
 */

/** @type {Package} */
export const KIT = {
    label: 'the admin UI kit (hryvinskyi/magento2-admin-ui)',
    directory: 'AdminUi',
    composer: join('vendor', 'hryvinskyi', 'magento2-admin-ui'),
    module: join('view', 'adminhtml', 'web', 'js', 'admin-ui', 'kit.js'),
    types: join('frontend', 'admin-ui', 'types', 'index.d.ts'),
};

/** The kit workspace's manifest, inside the kit's directory. */
export const KIT_MANIFEST = join('frontend', 'admin-ui', 'package.json');

/**
 * The places a package may be in, in the order they are tried
 *
 * @param {Package} pkg
 * @param {string} moduleRoot this module's directory
 * @returns {string[]}
 */
export function candidates(pkg, moduleRoot) {
    const found = [join(dirname(moduleRoot), pkg.directory)];
    let directory = moduleRoot;
    while (true) {
        found.push(join(directory, pkg.composer));
        if (dirname(directory) === directory) {
            return found;
        }
        directory = dirname(directory);
    }
}

/**
 * A package's directory; throws, naming every place it looked, when the package is in neither layout
 *
 * @param {Package} pkg
 * @param {string} [moduleRoot]
 * @returns {string}
 */
export function packageRoot(pkg, moduleRoot = MODULE_ROOT) {
    const places = candidates(pkg, moduleRoot);
    const found = places.find(function (place) {
        return existsSync(join(place, pkg.types)) && existsSync(join(place, pkg.module));
    });
    if (found === undefined) {
        throw new Error(`${pkg.label} was not found, so the Rich Snippets admin pages cannot be checked, built against or `
            + `tested with it. Check it out beside this module as ${pkg.directory}, or install it with Composer. `
            + `Looked for ${pkg.types} and ${pkg.module} in:\n  ${places.join('\n  ')}`);
    }

    return found;
}

/**
 * A package's built module
 *
 * @param {Package} pkg
 * @param {string} [moduleRoot]
 * @returns {string}
 */
export function packageModule(pkg, moduleRoot = MODULE_ROOT) {
    return join(packageRoot(pkg, moduleRoot), pkg.module);
}

/**
 * The kit workspace's manifest
 *
 * @param {string} [moduleRoot]
 * @returns {string}
 */
export function kitManifest(moduleRoot = MODULE_ROOT) {
    return join(packageRoot(KIT, moduleRoot), KIT_MANIFEST);
}
