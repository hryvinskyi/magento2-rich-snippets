/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview's entry module and composition root: it starts the island the page host printed as
 * `rich-snippets-overview`, reads the page's part of its document, and builds the page's one JSON client and its
 * endpoint — the only place that touches the browser's `fetch` and the page's address.
 */
import { createPageClient, startIsland, type Logger } from '@hryvinskyi/admin-ui';
import { parseOverviewDocument } from './adapters/overview-document.ts';
import { httpOverviewEndpoints } from './adapters/overview-endpoints.ts';
import { OverviewIsland } from './ui/OverviewIsland.tsx';

export const ISLAND = 'rich-snippets-overview';

const logger: Logger = { error: (message: string, detail?: unknown): void => console.error(message, detail) };

startIsland({
    name: ISLAND,
    logger,
    mount: (_root, page) => {
        const document = parseOverviewDocument(page);
        const client = createPageClient({
            formKey: document.formKey,
            baseUrl: window.location.href,
            fetch: (url, init) => window.fetch(url, init),
        });
        return (
            <OverviewIsland
                document={document}
                endpoints={httpOverviewEndpoints(client, document.url('data'))}
                logger={logger}
            />
        );
    },
});
