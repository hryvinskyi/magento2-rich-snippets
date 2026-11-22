/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page's edge: the readers on the contract's libraries, suggested answers and add answers, the page
 * document with its endpoints and permission, a malformed answer refused with the path of its fault, and the
 * endpoints on a recording client (what each request carries).
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isContractError, READ_DEADLINE_MS, type JsonClient, type JsonRequestOptions } from '@hryvinskyi/admin-ui';
import { libraryPhrases } from '../../test/library-harness.tsx';
import {
    contractAdd,
    contractLibrary,
    contractSuggestions,
    libraryChanging,
    libraryData,
    libraryDocument,
    LIBRARY_URLS,
    unavailableData,
} from '../../test/library-fixtures.ts';
import { parseAddAnswer, parseLibrary, parseLibraryData, parseTemplateAnswers } from './library-data.ts';
import { parseLibraryDocument, type LibraryEndpointName } from './library-document.ts';
import { httpLibraryEndpoints } from './library-endpoints.ts';

describe('the Library as the server writes it', () => {
    it('reads the contract\'s library: its groups, the recommended set, and each template with what it uses and asks', () => {
        const library = parseLibrary(contractLibrary(), '$');
        const organization = library.templates[0];

        assert.deepEqual(library.groups.map((group) => group.code), ['shop', 'product', 'category', 'content', 'blocks']);
        assert.deepEqual(library.recommended, ['organization', 'website-name', 'breadcrumbs', 'product', 'product-variants']);
        assert.equal(library.templates.length, 13);
        assert.deepEqual(
            [organization?.code, organization?.version, organization?.added, organization?.nextName, organization?.summary],
            ['organization', 1, true, 'Organization 2', 'Name, logo, contact, return policy'],
        );
        assert.deepEqual(organization?.uses.map((use) => [use.code, use.inShop]), [['return-policy', true], ['postal-address', true]]);
        assert.deepEqual(organization?.questions, [{ code: 'social_profiles', label: 'Social profiles' }]);
        assert.equal(library.templates.find((template) => template.code === 'faq')?.google.status, 'gone');
    });

    it('reads the first data with the template the address names, or none', () => {
        assert.equal(parseLibraryData(libraryData(contractLibrary(), 'faq'), '$').open, 'faq');
        assert.equal(parseLibraryData(libraryData(), '$').open, null);
        assert.equal(parseLibraryData(libraryData(), '$').library.templates.length, 13);
        assert.equal(parseLibraryData(libraryData(), '$').unavailable, false);
    });

    it('reads the first data of a page printed without its library', () => {
        const data = parseLibraryData(unavailableData(), '$');

        assert.deepEqual([data.unavailable, data.library.templates.length, data.open], [true, 0, null]);
    });

    it('reads a template\'s questions with each answer, its hint, tone and how many products it fills', () => {
        const answers = parseTemplateAnswers(contractSuggestions('product'), '$');
        const brand = answers.questions[0];

        assert.equal(answers.template, 'product');
        assert.deepEqual(answers.questions.map((question) => question.question), ['brand', 'gtin', 'description']);
        assert.deepEqual([brand?.default, brand?.total], ['attribute:manufacturer', 184]);
        assert.deepEqual(
            brand?.options.map((option) => [option.key, option.tone, option.fillCount, option.leftOut]),
            [
                ['attribute:manufacturer', 'ok', 31, false],
                ['attribute:brand', 'warn', 0, false],
                ['shop', 'ok', null, false],
                ['leave_out', 'warn', null, true],
            ],
        );
        assert.equal(parseTemplateAnswers(contractSuggestions('breadcrumbs'), '$').questions.length, 0);
    });

    it('reads an add\'s answer: the snippets added or reused, the refusal, and the library as it now stands', () => {
        const again = parseAddAnswer(contractAdd('again'), '$');
        const refused = parseAddAnswer(contractAdd('refused'), '$');

        assert.deepEqual(again.added.map((snippet) => [snippet.id, snippet.template, snippet.reused]), [
            [8, 'return-policy', true],
            [43, 'product', false],
        ]);
        assert.equal(again.refused, null);
        assert.deepEqual(refused.refused, { reason: 'include_loop' });
        assert.equal(refused.added.length, 0);
        assert.equal(refused.library.templates.length, 13);
    });

    it('refuses a library with an unknown Google status or a template without a name, naming where', () => {
        const status = libraryChanging('faq', { google: { status: 'maybe', short: '', long: '' } });
        const nameless = libraryChanging('faq', { name: '' });

        assert.throws(() => parseLibrary(status, '$'), (error: unknown) => isContractError(error) && /\["templates"\]\[8\]\["google"\]\["status"\]/.test(String(error)));
        assert.throws(() => parseLibrary(nameless, '$'), (error: unknown) => isContractError(error) && /\["templates"\]\[8\]\["name"\]/.test(String(error)));
    });

    it('reads the page document with its four endpoints and whether the admin may add templates', () => {
        const document = parseLibraryDocument(libraryDocument(libraryData(), libraryPhrases()));
        const looking = parseLibraryDocument(libraryDocument(libraryData(), libraryPhrases(), { canChange: false }));

        assert.equal(document.url('addRecommended'), LIBRARY_URLS['addRecommended']);
        assert.equal(document.allows('snippets'), true);
        assert.equal(looking.allows('snippets'), false);
        assert.throws(() => parseLibraryDocument({ ...libraryDocument(libraryData(), libraryPhrases()), urls: {} }));
    });
});

describe('the Library\'s endpoints', () => {
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

    const url = (name: LibraryEndpointName): string => LIBRARY_URLS[name] ?? '';
    const signal = new AbortController().signal;

    it('reads the library, and a template\'s questions by its code, within the read deadline', async () => {
        const list = recordingClient(contractLibrary());
        const suggestions = recordingClient(contractSuggestions('organization'));

        await httpLibraryEndpoints(list.client, url).list(signal);
        const answers = await httpLibraryEndpoints(suggestions.client, url).suggestions('organization', signal);

        assert.deepEqual(
            [...list.calls, ...suggestions.calls].map((call) => [call.method, call.url, call.options.query, call.options.timeoutMs]),
            [
                ['GET', LIBRARY_URLS['list'], undefined, READ_DEADLINE_MS],
                ['GET', LIBRARY_URLS['suggestions'], { template: 'organization' }, READ_DEADLINE_MS],
            ],
        );
        assert.equal(answers.template, 'organization');
    });

    it('posts a template with its answers by question and its key, and the recommended set with its own key', async () => {
        const { client, calls } = recordingClient(contractAdd('added'));
        const endpoints = httpLibraryEndpoints(client, url);

        await endpoints.add('product', new Map([['brand', 'shop'], ['gtin', 'leave_out']]), 'key-1', signal);
        await endpoints.add('faq', new Map(), 'key-2', signal);
        const set = await endpoints.addRecommended('key-3', signal);

        assert.deepEqual(calls.map((call) => [call.method, call.url, call.body]), [
            ['POST', LIBRARY_URLS['add'], { template: 'product', answers: { brand: 'shop', gtin: 'leave_out' }, idempotencyKey: 'key-1' }],
            ['POST', LIBRARY_URLS['add'], { template: 'faq', answers: {}, idempotencyKey: 'key-2' }],
            ['POST', LIBRARY_URLS['addRecommended'], { idempotencyKey: 'key-3' }],
        ]);
        assert.equal(set.added.length, 2);
        assert.ok(calls.every((call) => call.options.signal === signal));
    });
});
