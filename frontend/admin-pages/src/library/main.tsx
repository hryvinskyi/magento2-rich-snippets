/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page's entry module and composition root: it starts the island the page host printed as
 * `rich-snippets-library`, reads the page's part of its document, and builds the page's JSON client, its endpoints
 * and the idempotency keys — the only place that touches the browser's `fetch`, its random keys and the page's
 * address.
 */
import { createPageClient, startIsland, type Logger } from '@hryvinskyi/admin-ui';
import { parseLibraryDocument } from './adapters/library-document.ts';
import { httpLibraryEndpoints } from './adapters/library-endpoints.ts';
import { LibraryIsland } from './ui/LibraryIsland.tsx';

export const ISLAND = 'rich-snippets-library';

const logger: Logger = { error: (message: string, detail?: unknown): void => console.error(message, detail) };

startIsland({
    name: ISLAND,
    logger,
    mount: (_root, page) => {
        const document = parseLibraryDocument(page);
        const fetch = (url: string, init: RequestInit): Promise<Response> => window.fetch(url, init);
        const client = createPageClient({ formKey: document.formKey, baseUrl: window.location.href, fetch });
        const ports = {
            endpoints: httpLibraryEndpoints(client, document.url),
            keys: { next: (): string => crypto.randomUUID() },
            logger,
        };
        return <LibraryIsland document={document} ports={ports} />;
    },
});
