/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's adapters: its readers over the contract's answers, its endpoints on a recording client (what each
 * request carries), the download of a finding's pages and the shared file download on a scripted fetch (the file
 * saved under the server's name, each refusal the failure the page words).
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isContractError, isRequestError, READ_DEADLINE_MS, type JsonClient, type JsonRequestOptions } from '@hryvinskyi/admin-ui';
import { fetchFile, type DownloadResponse } from '../../shared/file-download.ts';
import {
    contractFindings,
    contractItems,
    contractRun,
    contractSearch,
    contractStatus,
    testData,
    TEST_URLS,
} from '../../test/test-page-fixtures.ts';
import { httpFindingsDownloads } from './findings-download.ts';
import {
    parseCheckStatus,
    parseFindingItems,
    parseFindingsTable,
    parsePageTest,
    parseSearchAnswer,
    parseTestData,
} from './test-data.ts';
import type { TestEndpointName } from './test-document.ts';
import { httpTestEndpoints, RUN_DEADLINE_MS } from './test-endpoints.ts';

describe('the Test page as the server writes it', () => {
    it('reads a page test: blocks with their attribution, the theme\'s markup, what is not output and the results', () => {
        const test = parsePageTest(contractRun('done'), '$');

        assert.deepEqual(test.blocks.map((block) => [block.name, block.verdict, block.size]), [['Product', 'ok', 1125], ['Breadcrumbs', 'ok', 356]]);
        assert.match(test.themeMarkup ?? '', /^The theme writes its own Product microdata/);
        assert.deepEqual(test.notOutput.map((row) => row.reason), ['other_kinds', 'conditions_not_met', 'switched_off', 'other_kinds', 'empty']);
        assert.deepEqual(test.features[0]?.missingRecommended, ['offers.shippingDetails']);
        assert.equal(test.failure, null);
    });

    it('reads a block no snippet wrote, a page with nothing found and a page that could not be read', () => {
        assert.deepEqual(parsePageTest(contractRun('problem'), '$').blocks[1], {
            code: null, snippetId: null, name: null, type: '', json: '{"@type": "Product",', size: 20, lines: 1, verdict: 'bad',
            summary: 'The JSON of this block cannot be read, so search engines ignore it.',
        });
        assert.equal(parsePageTest(contractRun('nothing'), '$').themeMarkup, null);
        assert.equal(parsePageTest(contractRun('failed'), '$').failure?.httpStatus, 503);
    });

    it('reads the search, every state of the check, the findings and the pages behind one', () => {
        assert.deepEqual(parseSearchAnswer(contractSearch('pages'), '$').pages[1], { ref: 'product:12', kind: 'product', label: 'Field satchel', path: '/bags/field-satchel.html' });
        assert.equal(parseSearchAnswer(contractSearch('address'), '$').address, '/checkout/cart');
        assert.equal(parseCheckStatus(contractStatus('running'), '$').progress?.percent, 54);
        assert.equal(parseCheckStatus(contractStatus('failed'), '$').failed, 'The last check stopped before it was finished.');
        assert.equal(parseCheckStatus(contractStatus('none'), '$').last, null);
        assert.deepEqual(parseFindingsTable(contractFindings('findings'), '$').findings.map((finding) => finding.tone), ['bad', 'warn', 'warn', 'note']);
        const items = parseFindingItems(contractItems('later'), '$');
        assert.deepEqual([items.key, items.page, items.total, items.items.length], ['merchant-listing-gtin13-product-ean', 2, 23, 3]);
    });

    it('reads the first data, and stops at the first wrong field naming its path', () => {
        assert.equal(parseTestData(testData('catalog'), '$').finding, 'merchant-listing-gtin13-product-ean');
        assert.throws(() => parseTestData(testData('page', { mode: 'both' }), '$'), (error: unknown) => isContractError(error) && error.path === '$["mode"]');
    });
});

interface Recorded {
    readonly method: 'get' | 'post';
    readonly url: string;
    readonly body: unknown;
    readonly options: JsonRequestOptions;
}

function recordingClient(answer: unknown): { client: JsonClient; calls: Recorded[] } {
    const calls: Recorded[] = [];
    return {
        calls,
        client: {
            get: (url, options) => {
                calls.push({ method: 'get', url, body: null, options });
                return Promise.resolve(answer);
            },
            post: (url, body, options) => {
                calls.push({ method: 'post', url, body, options });
                return Promise.resolve(answer);
            },
        },
    };
}

const url = (name: TestEndpointName): string => TEST_URLS[name] ?? '';

describe('the Test page\'s endpoints', () => {
    const signal = new AbortController().signal;

    it('names a page as the picker does, or by its path, with its store view, and gives the storefront time', async () => {
        const { client, calls } = recordingClient(contractRun('done'));
        const endpoints = httpTestEndpoints(client, url);

        await endpoints.run({ ref: 'product:12', kind: 'product', label: 'x', path: '' }, 2, signal);
        await endpoints.run({ ref: null, kind: 'other', label: '/checkout/cart', path: '/checkout/cart' }, 1, signal);

        assert.deepEqual(calls.map((call) => [call.method, call.url, call.body]), [
            ['post', TEST_URLS['run'], { page: 'product:12', store: 2 }],
            ['post', TEST_URLS['run'], { path: '/checkout/cart', store: 1 }],
        ]);
        assert.equal(calls[0]?.options.timeoutMs, RUN_DEADLINE_MS);
    });

    it('reads the search, the findings and the pages behind one with the read deadline, every store view as empty', async () => {
        const search = recordingClient(contractSearch('pages'));
        await httpTestEndpoints(search.client, url).search('sat', 1, signal);
        const findings = recordingClient(contractFindings('none'));
        await httpTestEndpoints(findings.client, url).findings(null, signal);
        const items = recordingClient(contractItems('first'));
        await httpTestEndpoints(items.client, url).findingItems('img', 3, 2, signal);

        assert.deepEqual(search.calls[0]?.options.query, { query: 'sat', store: '1' });
        assert.deepEqual(findings.calls[0]?.options.query, { store: '' });
        assert.deepEqual(items.calls[0]?.options.query, { key: 'img', store: '3', page: '2' });
        assert.equal(items.calls[0]?.options.timeoutMs, READ_DEADLINE_MS);
    });

    it('asks for a check with a POST and reads where the check stands with a GET', async () => {
        const { client, calls } = recordingClient(contractStatus('waiting'));
        const endpoints = httpTestEndpoints(client, url);

        assert.equal((await endpoints.requestCheck(signal)).state, 'requested');
        await endpoints.status(signal);

        assert.deepEqual(calls.map((call) => [call.method, call.url]), [['post', TEST_URLS['checkRequest']], ['get', TEST_URLS['checkStatus']]]);
    });
});

function respond(status: number, body: string, headers: Readonly<Record<string, string>> = {}, redirected = false): DownloadResponse {
    return { status, redirected, text: () => Promise.resolve(body), headers: { get: (name: string): string | null => headers[name] ?? null } };
}

describe('the download of a finding\'s pages', () => {
    const finding = parseFindingsTable(contractFindings('findings'), '$').findings[1];
    const csv = 'Product,SKU\nKey fob,LC-KF-TAN\n';

    function downloads(response: DownloadResponse | Error): { fetched: string[]; saved: [string, string][]; run: () => Promise<void> } {
        const fetched: string[] = [];
        const saved: [string, string][] = [];
        const port = httpFindingsDownloads({
            url: '/admin/richsnippets/test/findingsCsv/key/t7/',
            baseUrl: 'https://admin.test/admin/richsnippets/test/index/',
            fetch: (address) => {
                fetched.push(address);
                return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
            },
            save: (name, text) => void saved.push([name, text]),
        });
        return {
            fetched,
            saved,
            run: () => (finding === undefined ? Promise.reject(new Error('no finding')) : port.download(finding, null, new AbortController().signal)),
        };
    }

    it('fetches the finding\'s file for every store view and saves it under the server\'s name', async () => {
        const run = downloads(respond(200, csv, { 'Content-Disposition': 'attachment; filename="rich-snippets-ean.csv"' }));

        await run.run();

        assert.deepEqual(run.saved, [['rich-snippets-ean.csv', csv]]);
        const target = new URL(run.fetched[0] ?? '');
        assert.equal(target.searchParams.get('key'), 'merchant-listing-gtin13-product-ean');
        assert.equal(target.searchParams.get('store'), '');
        assert.equal(target.searchParams.get('isAjax'), 'true');
    });

    async function failureKind(response: DownloadResponse | Error): Promise<string> {
        const run = downloads(response);
        try {
            await run.run();
        } catch (error) {
            assert.equal(run.saved.length, 0, 'nothing is saved');
            return isRequestError(error) ? `${error.kind}${error.reason === null ? '' : `:${error.reason}`}` : 'other';
        }
        return 'none';
    }

    it('maps each refusal to the failure the page words, and saves nothing', async () => {
        assert.equal(await failureKind(respond(400, '{"error":"key"}')), 'rejected:key');
        assert.equal(await failureKind(respond(403, '{}')), 'forbidden');
        assert.equal(await failureKind(respond(502, '')), 'server');
        assert.equal(await failureKind(respond(200, '<html>login</html>', {}, true)), 'expired');
        assert.equal(await failureKind(respond(200, '{"ajaxExpired":1}')), 'expired');
        assert.equal(await failureKind(new TypeError('Failed to fetch')), 'network');
    });

    it('lets an abort of the caller through as it is, not as a failure to word', async () => {
        const controller = new AbortController();
        controller.abort();
        const reason = new Error('left the page');
        await assert.rejects(
            fetchFile({ url: '/x', baseUrl: 'https://admin.test/', fetch: () => Promise.reject(reason) }, controller.signal),
            (error: unknown) => error === reason,
        );
    });
});
