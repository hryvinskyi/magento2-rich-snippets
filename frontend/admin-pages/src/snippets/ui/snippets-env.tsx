/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Snippets page's components read beside the page's wording and links: the kinds of page by code, the
 * server's dates in the admin's words, the store views, and whether the admin may change snippets. The island
 * provides it once; reading it outside the provider fails loudly, as a component drawn without it is a wiring fault.
 */
import { createContext, type ComponentChildren, type JSX } from 'preact';
import { useContext } from 'preact/hooks';
import type { PageStoreView } from '@hryvinskyi/admin-ui';
import type { PageKind } from '../domain/model.ts';
import type { Days } from '../domain/wording.ts';

export interface SnippetsEnv {
    readonly kinds: readonly PageKind[];
    readonly kindsByCode: ReadonlyMap<string, PageKind>;
    readonly days: Days;
    readonly stores: readonly PageStoreView[];
    /** Whether the admin may switch, copy, delete, export and import snippets. */
    readonly canChange: boolean;
}

const EnvContext = createContext<SnippetsEnv | null>(null);

export function SnippetsEnvProvider({ env, children }: { readonly env: SnippetsEnv; readonly children: ComponentChildren }): JSX.Element {
    return <EnvContext.Provider value={env}>{children}</EnvContext.Provider>;
}

export class MissingSnippetsEnvError extends Error {
    constructor() {
        super('A Snippets page component was drawn outside its SnippetsEnvProvider.');
        this.name = 'MissingSnippetsEnvError';
    }
}

export function useSnippetsEnv(): SnippetsEnv {
    const env = useContext(EnvContext);
    if (env === null) {
        throw new MissingSnippetsEnvError();
    }
    return env;
}

/** The name of a store view the page lists; null for every store view, or one it does not list. */
export const storeNameOf = (stores: readonly PageStoreView[], store: number | null): string | null =>
    store === null ? null : (stores.find((view) => view.id === store)?.name ?? null);
