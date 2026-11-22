/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's endpoints over the page's JSON client: the list is a GET of `list?store=<id, or empty for every
 * store view>` with the read deadline; every write is a POST of a JSON body naming the store view the page shows, so
 * the list it answers is that one. Each answer is parsed before anyone sees it.
 */
import { READ_DEADLINE_MS, type JsonClient } from '@hryvinskyi/admin-ui';
import type { SnippetsEndpoints } from '../application/ports.ts';
import type { ImportAnswer, ImportChoice, ImportPreview, Listing, WriteAnswer } from '../domain/model.ts';
import type { SnippetsEndpointName } from './snippets-document.ts';
import { parseImportAnswer, parseImportPreview, parseListing, parseWriteAnswer } from './snippets-data.ts';

/** How long an import may take: the server reads and checks the whole file again. */
const IMPORT_DEADLINE_MS = 30_000;

export function httpSnippetsEndpoints(client: JsonClient, url: (name: SnippetsEndpointName) => string): SnippetsEndpoints {
    const write = async (name: SnippetsEndpointName, body: Readonly<Record<string, unknown>>, signal: AbortSignal): Promise<WriteAnswer> =>
        parseWriteAnswer(await client.post(url(name), body, { signal }), '$');
    return Object.freeze({
        async list(store: number | null, signal: AbortSignal): Promise<Listing> {
            const answer = await client.get(url('list'), {
                signal,
                timeoutMs: READ_DEADLINE_MS,
                query: { store: store === null ? '' : String(store) },
            });
            return parseListing(answer, '$');
        },
        setActive: (id: number, active: boolean, store: number | null, signal: AbortSignal): Promise<WriteAnswer> =>
            write('setActive', { id, active, store }, signal),
        duplicate: (id: number, idempotencyKey: string, store: number | null, signal: AbortSignal): Promise<WriteAnswer> =>
            write('duplicate', { id, idempotencyKey, store }, signal),
        remove: (id: number, store: number | null, signal: AbortSignal): Promise<WriteAnswer> =>
            write('delete', { id, store }, signal),
        async previewImport(file: string, signal: AbortSignal): Promise<ImportPreview> {
            const answer = await client.post(url('importPreview'), { file }, { signal, timeoutMs: IMPORT_DEADLINE_MS });
            return parseImportPreview(answer, '$');
        },
        async commitImport(
            file: string,
            choices: ReadonlyMap<string, ImportChoice>,
            idempotencyKey: string,
            store: number | null,
            signal: AbortSignal,
        ): Promise<ImportAnswer> {
            const answer = await client.post(
                url('importCommit'),
                { file, choices: Object.fromEntries(choices), idempotencyKey, store },
                { signal, timeoutMs: IMPORT_DEADLINE_MS },
            );
            return parseImportAnswer(answer, '$');
        },
    });
}
