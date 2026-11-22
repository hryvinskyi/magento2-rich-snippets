/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The expression builder, loaded through the admin's module loader the first time the Pages card needs it and
 * mounted with the rail between conditions. What the loader hands back is checked for a `mount` function before it
 * is used; anything else fails the load, naming the module.
 */
import type { ExpressionBuilderMountModule, MountExpressionBuilder } from 'Hryvinskyi_UiFormElements/js/expression-builder/mount';
import type { ConditionsWidget, ConditionsWidgets } from '../application/ports.ts';

export const BUILDER_MODULE = 'Hryvinskyi_UiFormElements/js/expression-builder/mount';

/** The admin's module loader: loads the modules named and hands them to `ready`, or the error to `failed`. */
export type ModuleLoader = (names: readonly string[], ready: (...modules: readonly unknown[]) => void, failed: (error: unknown) => void) => void;

export class BuilderLoadError extends Error {
    constructor(cause: unknown) {
        super(`The module ${BUILDER_MODULE} did not load as an expression builder.`, { cause });
        this.name = 'BuilderLoadError';
    }
}

const isMountModule = (value: unknown): value is ExpressionBuilderMountModule =>
    typeof value === 'object' && value !== null && 'mount' in value && typeof value.mount === 'function';

const widgetOf = (mount: MountExpressionBuilder): ConditionsWidget => ({
    mount: (host, options, onChange) =>
        mount(host, { value: options.value, builderConfig: options.config, label: options.label, joinerStyle: 'rail' }, onChange),
});

export function amdConditionsWidgets(loader: ModuleLoader): ConditionsWidgets {
    let loading: Promise<ConditionsWidget> | null = null;
    return Object.freeze({
        load(): Promise<ConditionsWidget> {
            loading ??= new Promise<ConditionsWidget>((resolve, reject) => {
                loader([BUILDER_MODULE], (module) => {
                    if (isMountModule(module)) {
                        resolve(widgetOf(module.mount));
                    } else {
                        reject(new BuilderLoadError(module));
                    }
                }, (error) => {
                    loading = null;
                    reject(new BuilderLoadError(error));
                });
            });
            return loading;
        },
    });
}
