/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the editor's components read beside the page's wording and links: the editor's store, its ports (the
 * endpoints, the expression builder, the draft's preview and save, a timer, idempotency keys, the clipboard and the
 * page's address), the kinds of page and their condition fields, the values a property starts with, the types a blank
 * snippet suggests, the store views, the snippets that use the edited one, the section the editor opens on, and what
 * the admin may do. The
 * island provides it once; reading it outside the provider fails loudly, as a component drawn without it is a wiring
 * fault.
 */
import { createContext, type ComponentChildren, type JSX } from 'preact';
import { useContext } from 'preact/hooks';
import type { Logger, PageStoreView } from '@hryvinskyi/admin-ui';
import type { EditorStore } from '../application/editor-store.ts';
import type {
    Clipboard,
    ConditionsWidgets,
    DraftGateway,
    EditorEndpoints,
    IdempotencyKeys,
    Navigation,
    Scheduler,
} from '../application/ports.ts';
import type { ConditionField, EditorSection, PageKind, UsedIn, ValueDefaults } from '../domain/model.ts';

export interface EditorPorts {
    readonly endpoints: EditorEndpoints;
    readonly widgets: ConditionsWidgets;
    readonly drafts: DraftGateway;
    readonly scheduler: Scheduler;
    readonly keys: IdempotencyKeys;
    readonly clipboard: Clipboard;
    readonly navigation: Navigation;
    readonly logger: Logger;
}

export interface EditorEnv {
    readonly store: EditorStore;
    readonly ports: EditorPorts;
    readonly kinds: readonly PageKind[];
    readonly kindsByCode: ReadonlyMap<string, PageKind>;
    readonly conditions: ReadonlyMap<string, readonly ConditionField[]>;
    readonly defaults: ValueDefaults;
    readonly typeSuggestions: readonly string[];
    readonly stores: readonly PageStoreView[];
    readonly usedIn: readonly UsedIn[];
    readonly isNew: boolean;
    readonly section: EditorSection;
    /** Whether the admin may change and save snippets. */
    readonly canChange: boolean;
    /** Whether the admin may test pages. */
    readonly canTest: boolean;
    /** Reports a fault that is not a failed request. */
    readonly report: (error: unknown) => void;
}

const EnvContext = createContext<EditorEnv | null>(null);

export function EditorEnvProvider({ env, children }: { readonly env: EditorEnv; readonly children: ComponentChildren }): JSX.Element {
    return <EnvContext.Provider value={env}>{children}</EnvContext.Provider>;
}

export class MissingEditorEnvError extends Error {
    constructor() {
        super('A snippet editor component was drawn outside its EditorEnvProvider.');
        this.name = 'MissingEditorEnvError';
    }
}

export function useEditorEnv(): EditorEnv {
    const env = useContext(EnvContext);
    if (env === null) {
        throw new MissingEditorEnvError();
    }
    return env;
}
