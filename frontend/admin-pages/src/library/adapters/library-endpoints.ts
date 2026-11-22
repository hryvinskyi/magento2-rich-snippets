/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page's endpoints over the page's JSON client: the library and a template's questions are GETs with the
 * read deadline (`suggestions?template=<code>`); the two adds are POSTs of a JSON body with the idempotency key of the
 * admin's intent, and the client's deadline. Each answer is parsed before anyone sees it.
 */
import { READ_DEADLINE_MS, type JsonClient } from '@hryvinskyi/admin-ui';
import type { LibraryEndpoints } from '../application/ports.ts';
import type { AddAnswer, Library, TemplateAnswers } from '../domain/model.ts';
import type { LibraryEndpointName } from './library-document.ts';
import { parseAddAnswer, parseLibrary, parseTemplateAnswers } from './library-data.ts';

export function httpLibraryEndpoints(client: JsonClient, url: (name: LibraryEndpointName) => string): LibraryEndpoints {
    return Object.freeze({
        async list(signal: AbortSignal): Promise<Library> {
            return parseLibrary(await client.get(url('list'), { signal, timeoutMs: READ_DEADLINE_MS }), '$');
        },
        async suggestions(template: string, signal: AbortSignal): Promise<TemplateAnswers> {
            const answer = await client.get(url('suggestions'), { signal, timeoutMs: READ_DEADLINE_MS, query: { template } });
            return parseTemplateAnswers(answer, '$');
        },
        async add(
            template: string,
            answers: ReadonlyMap<string, string>,
            idempotencyKey: string,
            signal: AbortSignal,
        ): Promise<AddAnswer> {
            const body = { template, answers: Object.fromEntries(answers), idempotencyKey };
            return parseAddAnswer(await client.post(url('add'), body, { signal }), '$');
        },
        async addRecommended(idempotencyKey: string, signal: AbortSignal): Promise<AddAnswer> {
            return parseAddAnswer(await client.post(url('addRecommended'), { idempotencyKey }, { signal }), '$');
        },
    });
}
