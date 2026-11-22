/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's catalog endpoints over the page's JSON client: the reads are GETs with the read deadline —
 * `fields?sample=&store=`, `includeCandidates?id=`, `builderConfig?kinds=a,b&store=` — and the reads that carry the
 * conditions are POSTs that change nothing, with the same deadline: the samples, `{"kinds", "conditions", "store"}`,
 * and the placement count, `{"pageKinds", "conditions", "storeIds", "store"}`. A store view left out is sent empty (or
 * null in a body), for the default one. Each answer is parsed before anyone sees it.
 */
import { READ_DEADLINE_MS, type JsonClient } from '@hryvinskyi/admin-ui';
import type { EditorEndpoints } from '../application/ports.ts';
import type { FieldCatalog, IncludeCandidate, Placement, PlacementCount, Sample } from '../domain/model.ts';
import type { EditorEndpointName } from './editor-document.ts';
import { parseBuilderConfig, parseCandidates, parseFieldCatalog, parsePlacementCount, parseSamples } from './editor-data.ts';

const storeParam = (store: number | null): string => (store === null ? '' : String(store));

export function httpEditorEndpoints(client: JsonClient, url: (name: EditorEndpointName) => string): EditorEndpoints {
    const read = (name: EditorEndpointName, query: Readonly<Record<string, string>>, signal: AbortSignal): Promise<unknown> =>
        client.get(url(name), { signal, timeoutMs: READ_DEADLINE_MS, query });
    return Object.freeze({
        async fields(sample: string | null, store: number | null, signal: AbortSignal): Promise<FieldCatalog> {
            return parseFieldCatalog(await read('fields', { sample: sample ?? '', store: storeParam(store) }, signal), '$');
        },
        async samples(kinds: readonly string[], conditions: string, store: number | null, signal: AbortSignal): Promise<readonly Sample[]> {
            const answer = await client.post(url('samples'), { kinds, conditions, store }, { signal, timeoutMs: READ_DEADLINE_MS });
            return parseSamples(answer, '$');
        },
        async includeCandidates(id: number | null, signal: AbortSignal): Promise<readonly IncludeCandidate[]> {
            return parseCandidates(await read('includeCandidates', { id: id === null ? '' : String(id) }, signal), '$');
        },
        async builderConfig(kinds: readonly string[], store: number | null, signal: AbortSignal): Promise<Readonly<Record<string, unknown>>> {
            return parseBuilderConfig(await read('builderConfig', { kinds: kinds.join(','), store: storeParam(store) }, signal), '$');
        },
        async placementCount(placement: Placement, store: number | null, signal: AbortSignal): Promise<PlacementCount> {
            const answer = await client.post(
                url('placementCount'),
                { pageKinds: placement.pageKinds, conditions: placement.conditions, storeIds: placement.storeIds, store },
                { signal, timeoutMs: READ_DEADLINE_MS },
            );
            return parsePlacementCount(answer, '$');
        },
    });
}
