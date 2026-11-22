/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's edge: the readers on the contract's list and on the preview and import answers, the endpoints
 * on a recording client (what each request carries), the export download on a scripted fetch (the file saved under
 * the server's name, each refusal mapped to the failure the page words), and dates read as the server writes them.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    isContractError,
    isRequestError,
    READ_DEADLINE_MS,
    type JsonClient,
    type JsonRequestOptions,
} from '@hryvinskyi/admin-ui';
import { contractList, emptyList, importPreview, KINDS, OUTPUT_LINE, snippetsData, SNIPPETS_URLS } from '../../test/snippets-fixtures.ts';
import { createDays } from './days.ts';
import { httpExportDownloads, type DownloadResponse } from './export-download.ts';
import {
    parseImportAnswer,
    parseImportPreview,
    parseListing,
    parseSnippetsData,
    parseWriteAnswer,
} from './snippets-data.ts';
import type { SnippetsEndpointName } from './snippets-document.ts';
import { httpSnippetsEndpoints } from './snippets-endpoints.ts';

describe('the Snippets page as the server writes it', () => {
    it('reads the contract\'s list: each row, the counts, and the kinds of page counted by code', () => {
        const listing = parseListing(contractList(), '$');

        assert.deepEqual(listing.rows.map((row) => row.id), [1, 3, 6, 8, 9, 11]);
        assert.deepEqual(listing.rows[1]?.flags, { bad: 'No offer: Google needs one', warn: null, note: 'EAN is empty for 23 products' });
        assert.deepEqual(listing.rows[5]?.uses, [{ code: 'gone-block', name: null }]);
        assert.equal(listing.counts.kinds.get('product'), 2);
        assert.deepEqual([listing.counts.on, listing.counts.off, listing.counts.blocks], [4, 1, 1]);
    });

    it('reads an object the server had nothing for, written as an empty list, as empty', () => {
        assert.equal(parseListing(emptyList(), '$').counts.kinds.size, 0);
    });

    it('reads the first data: the kinds of page in the server\'s order and its output line', () => {
        const data = parseSnippetsData(snippetsData(), '$');

        assert.deepEqual(data.kinds.map((kind) => kind.code), KINDS.map((kind) => kind.code));
        assert.equal(data.outputLine, OUTPUT_LINE);
        assert.equal(data.list.rows.length, 6);
    });

    it('reads a write\'s answer: the list, and the refusal with the snippets that use a block', () => {
        const refused = parseWriteAnswer(
            { ...contractList(), id: null, refused: { reason: 'in_use', includers: [{ code: 'faq', name: 'FAQ' }] } },
            '$',
        );
        const copied = parseWriteAnswer({ ...contractList(), id: 12, refused: null }, '$');

        assert.deepEqual(refused.refused, { reason: 'in_use', includers: [{ code: 'faq', name: 'FAQ' }] });
        assert.equal(copied.id, 12);
        assert.equal(copied.list.rows.length, 6);
    });

    it('reads a preview: each snippet with its standing, the choices offered and the problems with their values', () => {
        const preview = parseImportPreview(importPreview(), '$');

        assert.equal(preview.source, 'shop.example');
        assert.deepEqual(preview.snippets.map((entry) => entry.status), ['new', 'changed', 'identical', 'changed']);
        assert.deepEqual(preview.snippets[1]?.choices, ['replace', 'keep_both', 'skip']);
        assert.equal(preview.snippets[1]?.problems[0]?.params.get('store'), 'trade_de');
    });

    it('refuses a choice it does not know, naming the path', () => {
        const value = importPreview();
        const broken = { ...value, snippets: [{ ...(Array.isArray(value['snippets']) ? value['snippets'][0] : {}), choices: ['merge'] }] };

        assert.throws(
            () => parseImportPreview(broken, '$'),
            (error: unknown) => isContractError(error) && error.path === '$["snippets"][0]["choices"][0]',
        );
    });

    it('reads an import\'s answer: the list after it and how many snippets it added or replaced', () => {
        const answer = parseImportAnswer({
            ...contractList(),
            imported: [
                { id: 12, code: 'breadcrumbs', name: 'Breadcrumbs', choice: 'add' },
                { id: 3, code: 'product', name: 'Product', choice: 'replace' },
                { id: 1, code: 'organization', name: 'Organization', choice: 'skip' },
            ],
            count: 3,
        }, '$');

        assert.equal(answer.imported, 2);
        assert.equal(answer.list.rows.length, 6);
    });
});

describe('the Snippets page\'s endpoints', () => {
    interface Recorded {
        readonly method: 'GET' | 'POST';
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
                    calls.push({ method: 'GET', url, body: null, options });
                    return Promise.resolve(answer);
                },
                post: (url, body, options) => {
                    calls.push({ method: 'POST', url, body, options });
                    return Promise.resolve(answer);
                },
            },
        };
    }

    const url = (name: SnippetsEndpointName): string => SNIPPETS_URLS[name] ?? '';
    const signal = new AbortController().signal;

    it('reads the list of a store view, or of every one, within the read deadline', async () => {
        const { client, calls } = recordingClient(contractList());
        const endpoints = httpSnippetsEndpoints(client, url);

        await endpoints.list(2, signal);
        await endpoints.list(null, signal);

        assert.deepEqual(calls.map((call) => [call.method, call.url, call.options.query, call.options.timeoutMs]), [
            ['GET', SNIPPETS_URLS['list'], { store: '2' }, READ_DEADLINE_MS],
            ['GET', SNIPPETS_URLS['list'], { store: '' }, READ_DEADLINE_MS],
        ]);
    });

    it('posts the state a switch is wanted in, a copy with its key, and a delete, each with the store view picked', async () => {
        const { client, calls } = recordingClient({ ...contractList(), id: null, refused: null });
        const endpoints = httpSnippetsEndpoints(client, url);

        await endpoints.setActive(6, true, 2, signal);
        await endpoints.duplicate(8, 'key-1', null, signal);
        await endpoints.remove(8, null, signal);

        assert.deepEqual(calls.map((call) => [call.url, call.body]), [
            [SNIPPETS_URLS['setActive'], { id: 6, active: true, store: 2 }],
            [SNIPPETS_URLS['duplicate'], { id: 8, idempotencyKey: 'key-1', store: null }],
            [SNIPPETS_URLS['delete'], { id: 8, store: null }],
        ]);
    });

    it('posts a file\'s text for its preview, then the file, the choices by code and the key to import it', async () => {
        const preview = recordingClient(importPreview());
        await httpSnippetsEndpoints(preview.client, url).previewImport('{"x":1}', signal);

        const commit = recordingClient({ ...contractList(), imported: [], count: 0 });
        await httpSnippetsEndpoints(commit.client, url).commitImport('{"x":1}', new Map([['product', 'skip']]), 'key-2', 2, signal);

        assert.deepEqual([preview.calls[0]?.url, preview.calls[0]?.body], [SNIPPETS_URLS['importPreview'], { file: '{"x":1}' }]);
        assert.deepEqual(
            [commit.calls[0]?.url, commit.calls[0]?.body],
            [SNIPPETS_URLS['importCommit'], { file: '{"x":1}', choices: { product: 'skip' }, idempotencyKey: 'key-2', store: 2 }],
        );
        assert.ok((preview.calls[0]?.options.timeoutMs ?? 0) > READ_DEADLINE_MS, 'a file takes longer than a read');
    });
});

describe('the export download', () => {
    interface Fetched {
        readonly url: string;
        readonly init: RequestInit;
    }

    function respond(status: number, body: string, headers: Readonly<Record<string, string>> = {}, redirected = false): DownloadResponse {
        return {
            status,
            redirected,
            text: () => Promise.resolve(body),
            headers: { get: (name: string): string | null => headers[name] ?? null },
        };
    }

    const file = JSON.stringify({ hryvinskyi_rich_snippets: { version: 1 }, snippets: [{ code: 'faq' }, { code: 'product' }] });

    function download(response: DownloadResponse | Error): { fetched: Fetched[]; saved: [string, string][]; downloads: ReturnType<typeof httpExportDownloads> } {
        const fetched: Fetched[] = [];
        const saved: [string, string][] = [];
        const downloads = httpExportDownloads({
            url: '/admin/richsnippets/snippets/export/key/a5/',
            baseUrl: 'https://admin.test/admin/richsnippets/snippets/index/',
            fetch: (url, init) => {
                fetched.push({ url, init });
                return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
            },
            save: (name, text) => void saved.push([name, text]),
        });
        return { fetched, saved, downloads };
    }

    it('fetches one snippet\'s export and saves it under the server\'s file name', async () => {
        const run = download(respond(200, file, { 'Content-Disposition': 'attachment; filename="faq.json"' }));

        const exported = await run.downloads.one(8, new AbortController().signal);

        assert.deepEqual(exported, { fileName: 'faq.json', count: 2 });
        assert.deepEqual(run.saved, [['faq.json', file]]);
        const target = new URL(run.fetched[0]?.url ?? '');
        assert.equal(target.origin + target.pathname, 'https://admin.test/admin/richsnippets/snippets/export/key/a5/');
        assert.equal(target.searchParams.get('id'), '8');
        assert.equal(run.fetched[0]?.init.method, 'GET');
    });

    it('fetches every snippet without an id', async () => {
        const run = download(respond(200, file, { 'Content-Disposition': 'attachment; filename="rich-snippets-shop.json"' }));

        const exported = await run.downloads.all(new AbortController().signal);

        assert.equal(exported.fileName, 'rich-snippets-shop.json');
        assert.equal(new URL(run.fetched[0]?.url ?? '').searchParams.has('id'), false);
    });

    async function failureKind(response: DownloadResponse | Error): Promise<string> {
        const run = download(response);
        try {
            await run.downloads.one(8, new AbortController().signal);
        } catch (error) {
            assert.equal(run.saved.length, 0, 'nothing is saved');
            return isRequestError(error) ? `${error.kind}${error.reason === null ? '' : `:${error.reason}`}` : 'other';
        }
        return 'none';
    }

    it('maps each refusal to the failure the page words, and saves nothing', async () => {
        const named = { 'Content-Disposition': 'attachment; filename="faq.json"' };

        assert.equal(await failureKind(respond(400, '{"error":"unknown_snippet"}')), 'rejected:unknown_snippet');
        assert.equal(await failureKind(respond(403, '{}')), 'forbidden');
        assert.equal(await failureKind(respond(500, '{"error":"error"}')), 'server');
        assert.equal(await failureKind(respond(200, '<html>login</html>', {}, true)), 'expired');
        assert.equal(await failureKind(respond(200, '<html>login</html>')), 'expired');
        assert.equal(await failureKind(respond(200, '{"snippets":[]}', named)), 'server');
        assert.equal(await failureKind(respond(200, file)), 'server');
        assert.equal(await failureKind(new TypeError('Failed to fetch')), 'network');
    });
});

describe('dates', () => {
    it('reads the server\'s time as UTC and words it in the admin\'s language', () => {
        const days = createDays('en_GB', 'UTC');

        assert.equal(days.short('2026-11-12 14:30:00'), '12 Nov');
        assert.equal(days.long('2026-11-12 14:30:00'), '12 November');
        assert.equal(days.full('2026-11-13 10:00:00'), '13 November 2026');
        assert.equal(days.short('not a date'), 'not a date');
    });

    it('shows the server\'s time in the zone asked for', () => {
        assert.equal(createDays('en_GB', 'Pacific/Auckland').short('2026-11-12 14:30:00'), '13 Nov');
    });
});
