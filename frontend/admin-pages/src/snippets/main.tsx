/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's entry module and composition root: it starts the island the page host printed as
 * `rich-snippets-snippets`, reads the page's part of its document, and builds the page's JSON client, its endpoints,
 * the export download, and the idempotency keys — the only place that touches the browser's `fetch`, its file saving,
 * its random keys and the page's address.
 */
import { createPageClient, startIsland, type Logger } from '@hryvinskyi/admin-ui';
import { browserSave } from '../shared/browser-save.ts';
import { httpExportDownloads } from './adapters/export-download.ts';
import { parseSnippetsDocument } from './adapters/snippets-document.ts';
import { httpSnippetsEndpoints } from './adapters/snippets-endpoints.ts';
import { SnippetsIsland } from './ui/SnippetsIsland.tsx';

export const ISLAND = 'rich-snippets-snippets';

const logger: Logger = { error: (message: string, detail?: unknown): void => console.error(message, detail) };

startIsland({
    name: ISLAND,
    logger,
    mount: (_root, page) => {
        const document = parseSnippetsDocument(page);
        const fetch = (url: string, init: RequestInit): Promise<Response> => window.fetch(url, init);
        const client = createPageClient({ formKey: document.formKey, baseUrl: window.location.href, fetch });
        const ports = {
            endpoints: httpSnippetsEndpoints(client, document.url),
            downloads: httpExportDownloads({ url: document.url('export'), baseUrl: window.location.href, fetch, save: browserSave(window.document, 'application/json') }),
            keys: { next: (): string => crypto.randomUUID() },
            logger,
        };
        return <SnippetsIsland document={document} ports={ports} />;
    },
});
