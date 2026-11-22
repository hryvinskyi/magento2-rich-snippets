/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's entry module and composition root: it starts the island the page host printed as
 * `rich-snippets-test`, reads the page's part of its document, and builds the page's JSON client, its endpoints, the
 * download of a finding's pages, the clock that runs a task later and the page's address — the only place that
 * touches the browser's `fetch`, its timers, its file saving and its history.
 */
import { createPageClient, startIsland, type Logger } from '@hryvinskyi/admin-ui';
import { browserSave } from '../shared/browser-save.ts';
import { httpFindingsDownloads } from './adapters/findings-download.ts';
import { parseTestDocument } from './adapters/test-document.ts';
import { httpTestEndpoints } from './adapters/test-endpoints.ts';
import { TestIsland } from './ui/TestIsland.tsx';

export const ISLAND = 'rich-snippets-test';

const logger: Logger = { error: (message: string, detail?: unknown): void => console.error(message, detail) };

/** Writes what the page shows into its query, keeping the rest of its address, without a new history entry. */
function showInAddress(params: ReadonlyMap<string, string>): void {
    const address = new URL(window.location.href);
    address.search = '';
    for (const [name, value] of params) {
        address.searchParams.set(name, value);
    }
    if (address.href !== window.location.href) {
        window.history.replaceState(window.history.state, '', address.href);
    }
}

startIsland({
    name: ISLAND,
    logger,
    mount: (_root, page) => {
        const document = parseTestDocument(page);
        const fetch = (url: string, init: RequestInit): Promise<Response> => window.fetch(url, init);
        const client = createPageClient({ formKey: document.formKey, baseUrl: window.location.href, fetch });
        const ports = {
            endpoints: httpTestEndpoints(client, document.url),
            downloads: httpFindingsDownloads({
                url: document.url('findingsCsv'),
                baseUrl: window.location.href,
                fetch,
                save: browserSave(window.document, 'text/csv'),
            }),
            scheduler: {
                after: (ms: number, task: () => void): (() => void) => {
                    const timer = window.setTimeout(task, ms);
                    return (): void => window.clearTimeout(timer);
                },
            },
            address: { show: showInAddress },
            logger,
        };
        return <TestIsland document={document} ports={ports} />;
    },
});
