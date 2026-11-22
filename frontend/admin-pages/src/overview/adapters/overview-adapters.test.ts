/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isContractError, type JsonClient, type JsonRequestOptions } from '@hryvinskyi/admin-ui';
import { DATA_URL, stateData } from '../../test/fixtures.ts';
import { parseOverviewData } from './overview-data.ts';
import { httpOverviewEndpoints } from './overview-endpoints.ts';

describe('the Overview as the server writes it', () => {
    it('reads every state of the contract', () => {
        for (const state of ['healthy', 'attention', 'deutsch', 'none', 'off'] as const) {
            assert.doesNotThrow(() => parseOverviewData(stateData(state), '$'), state);
        }
    });

    it('reads a live shop with things to fix: the status, the facts, the page map, the Google card and the list', () => {
        const data = parseOverviewData(stateData('attention'), '$');

        assert.equal(data.state, 'live');
        assert.deepEqual(data.status.tone, 'invalid');
        assert.equal(data.status.action?.link.route, 'hryvinskyi_richsnippets/test/index');
        assert.deepEqual(data.facts.map((fact) => fact.warn), [false, false, false, true]);
        assert.deepEqual(data.kinds.rows[0]?.see?.params, new Map([['page', 'home']]));
        assert.equal(data.kinds.rows[4]?.see, null);
        assert.deepEqual(data.features.rows.map((row) => row.status), ['partly', 'met', 'met', 'met', 'met', 'met', 'no_result']);
        assert.deepEqual(data.attention.items.map((item) => item.tone), ['bad', 'warn', 'warn', 'info']);
        assert.deepEqual(
            [...(data.attention.items[0]?.link.params ?? [])],
            [['mode', 'catalog'], ['finding', 'merchant-listing-image']],
        );
        assert.equal(data.attention.none, null);
    });

    it('reads one store view and the store it names', () => {
        const data = parseOverviewData(stateData('deutsch'), '$');

        assert.equal(data.store, 2);
        assert.equal(data.kinds.rows[3]?.see?.params.get('store'), '2');
    });

    it('reads a shop with nothing added: no action, rows with nothing, and the Google card\'s note', () => {
        const data = parseOverviewData(stateData('none'), '$');

        assert.equal(data.status.action, null);
        assert.equal(data.kinds.rows[0]?.empty, 'Nothing.');
        assert.deepEqual(data.features.empty, {
            title: 'Nothing to show yet',
            text: 'No snippet is added, so the pages carry no structured data from this module yet.',
        });
    });

    it('refuses a verdict it does not know, naming the path', () => {
        const data = stateData('healthy');
        const features = data['features'] as Record<string, unknown>;
        const rows = features['rows'] as Record<string, unknown>[];
        const broken = { ...data, features: { ...features, rows: [{ ...rows[0], status: 'great' }] } };

        assert.throws(
            () => parseOverviewData(broken, '$'),
            (error: unknown) => isContractError(error) && error.path === '$["features"]["rows"][0]["status"]',
        );
    });
});

describe('the Overview\'s endpoint', () => {
    function recordingClient(answer: unknown): { client: JsonClient; calls: { url: string; options: JsonRequestOptions }[] } {
        const calls: { url: string; options: JsonRequestOptions }[] = [];
        const client: JsonClient = {
            get: (url: string, options: JsonRequestOptions): Promise<unknown> => {
                calls.push({ url, options });
                return Promise.resolve(answer);
            },
            post: () => Promise.reject(new Error('The Overview never writes')),
        };
        return { client, calls };
    }

    it('reads one store view by its id, with the read deadline and the caller\'s signal, and parses the answer', async () => {
        const { client, calls } = recordingClient(stateData('deutsch'));
        const signal = new AbortController().signal;

        const data = await httpOverviewEndpoints(client, DATA_URL).read(2, signal);

        assert.equal(data.store, 2);
        assert.equal(calls[0]?.url, DATA_URL);
        assert.deepEqual(calls[0]?.options.query, { store: '2' });
        assert.equal(calls[0]?.options.signal, signal);
        assert.equal(calls[0]?.options.timeoutMs, 8_000);
    });

    it('asks for every store view with an empty store', async () => {
        const { client, calls } = recordingClient(stateData('healthy'));

        await httpOverviewEndpoints(client, DATA_URL).read(null, new AbortController().signal);

        assert.deepEqual(calls[0]?.options.query, { store: '' });
    });

    it('refuses an answer of the wrong shape before anyone sees it', async () => {
        const { client } = recordingClient({ state: 'live' });

        await assert.rejects(httpOverviewEndpoints(client, DATA_URL).read(null, new AbortController().signal), isContractError);
    });
});
