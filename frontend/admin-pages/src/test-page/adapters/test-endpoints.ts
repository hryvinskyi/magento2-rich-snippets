/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's endpoints over the page's JSON client. Reads are GETs with the read deadline: the search
 * (`?query=&store=`), where the check stands, the findings (`?store=`, empty for every store view) and the pages
 * behind one (`?key=&store=&page=`). The page test is a POST naming the page as the picker does (`{"page": "kind:id"}`)
 * or by its path (`{"path"}`), with its store view; it reads the storefront, so it may take as long as the server's
 * own deadline for it and a little more. "Check now" is a POST with no body. Each answer is parsed before anyone sees
 * it.
 */
import { READ_DEADLINE_MS, type JsonClient } from '@hryvinskyi/admin-ui';
import type { TestEndpoints } from '../application/ports.ts';
import type { CheckStatus, FindingItems, FindingsTable, PageTest, PickedPage, SearchAnswer } from '../domain/model.ts';
import type { TestEndpointName } from './test-document.ts';
import { parseCheckStatus, parseFindingItems, parseFindingsTable, parsePageTest, parseSearchAnswer } from './test-data.ts';

/** How long a page test may take: the server gives the storefront 15 seconds. */
export const RUN_DEADLINE_MS = 20_000;

const storeParam = (store: number | null): string => (store === null ? '' : String(store));

export function httpTestEndpoints(client: JsonClient, url: (name: TestEndpointName) => string): TestEndpoints {
    const read = (name: TestEndpointName, query: Readonly<Record<string, string>>, signal: AbortSignal): Promise<unknown> =>
        client.get(url(name), { signal, timeoutMs: READ_DEADLINE_MS, query });
    return Object.freeze({
        async search(query: string, store: number, signal: AbortSignal): Promise<SearchAnswer> {
            return parseSearchAnswer(await read('search', { query, store: String(store) }, signal), '$');
        },
        async run(page: PickedPage, store: number, signal: AbortSignal): Promise<PageTest> {
            const named = page.ref === null ? { path: page.path } : { page: page.ref };
            const answer = await client.post(url('run'), { ...named, store }, { signal, timeoutMs: RUN_DEADLINE_MS });
            return parsePageTest(answer, '$');
        },
        async status(signal: AbortSignal): Promise<CheckStatus> {
            return parseCheckStatus(await read('checkStatus', {}, signal), '$');
        },
        async requestCheck(signal: AbortSignal): Promise<CheckStatus> {
            return parseCheckStatus(await client.post(url('checkRequest'), {}, { signal }), '$');
        },
        async findings(store: number | null, signal: AbortSignal): Promise<FindingsTable> {
            return parseFindingsTable(await read('findings', { store: storeParam(store) }, signal), '$');
        },
        async findingItems(key: string, store: number | null, page: number, signal: AbortSignal): Promise<FindingItems> {
            const query = { key, store: storeParam(store), page: String(page) };
            return parseFindingItems(await read('findingItems', query, signal), '$');
        },
    });
}
