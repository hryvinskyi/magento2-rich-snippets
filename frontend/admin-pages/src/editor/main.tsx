/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's entry module and composition root: it starts the island the page host printed as
 * `rich-snippets-editor`, reads the page's part of its document, and builds the editor's store, the page's JSON
 * client, the editor's endpoints, the draft's preview and save, the expression builder loaded through the admin's
 * module loader, and the browser's timer, random keys, clipboard and address — the only place that touches the
 * browser's `fetch`, the page's address, the clipboard, the timers and the module loader.
 */
import { createPageClient, startIsland, type Logger } from '@hryvinskyi/admin-ui';
import { amdConditionsWidgets, type ModuleLoader } from './adapters/conditions-loader.ts';
import { httpDraftGateway } from './adapters/draft-endpoints.ts';
import { parseEditorDocument } from './adapters/editor-document.ts';
import { httpEditorEndpoints } from './adapters/editor-endpoints.ts';
import { createEditorStore } from './application/editor-store.ts';
import type { Clipboard, IdempotencyKeys, Navigation, Scheduler } from './application/ports.ts';
import { EditorWorkbench } from './ui/EditorWorkbench.tsx';

export const ISLAND = 'rich-snippets-editor';

const logger: Logger = { error: (message: string, detail?: unknown): void => console.error(message, detail) };

/** The admin's module loader, as the page defines it; a page without one fails the builder's load, not the page. */
const moduleLoader: ModuleLoader = (names, ready, failed) => {
    const loader: unknown = Reflect.get(window, 'require');
    if (typeof loader !== 'function') {
        failed(new Error('The admin page has no module loader.'));
        return;
    }
    Reflect.apply(loader, window, [[...names], ready, failed]);
};

const scheduler: Scheduler = {
    after: (milliseconds, run) => {
        const timer = window.setTimeout(run, milliseconds);
        return () => window.clearTimeout(timer);
    },
};

const keys: IdempotencyKeys = { next: () => window.crypto.randomUUID() };

const clipboard: Clipboard = { write: (text) => window.navigator.clipboard.writeText(text) };

const navigation: Navigation = {
    go: (url) => window.location.assign(url),
    replace: (url) => window.history.replaceState(window.history.state, '', url),
    reload: () => window.location.reload(),
};

startIsland({
    name: ISLAND,
    logger,
    mount: (_root, page) => {
        const document = parseEditorDocument(page);
        const fetch = (url: string, init: RequestInit): Promise<Response> => window.fetch(url, init);
        const client = createPageClient({ formKey: document.formKey, baseUrl: window.location.href, fetch });
        const store = createEditorStore(document.data.snippet);
        const ports = {
            endpoints: httpEditorEndpoints(client, document.url),
            widgets: amdConditionsWidgets(moduleLoader),
            drafts: httpDraftGateway(client, document.url),
            scheduler,
            keys,
            clipboard,
            navigation,
            logger,
        };
        return <EditorWorkbench document={document} store={store} ports={ports} />;
    },
});
