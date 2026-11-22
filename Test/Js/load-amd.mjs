/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const moduleRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

/**
 * Run an AMD module of the package in a fresh context and return what its factory returns; the module may depend
 * only on the modules given by name, and sees no global but the ones given
 *
 * @param {string} path the module's path from the package root
 * @param {Record<string, unknown>} dependencies
 * @param {Record<string, unknown>} globals
 * @returns {any}
 */
export function loadAmd(path, dependencies = {}, globals = {}) {
    let exported;
    const define = (names, factory) => {
        exported = factory(...names.map((name) => {
            if (!(name in dependencies)) {
                throw new Error(`${path} asks for ${name}, which the test does not provide`);
            }
            return dependencies[name];
        }));
    };
    vm.runInNewContext(readFileSync(resolve(moduleRoot, path), 'utf8'), { ...globals, define }, { filename: path });

    return exported;
}
