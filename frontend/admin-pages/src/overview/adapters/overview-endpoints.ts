/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview's one read, as the page needs it, and its adapter over the page's JSON client: a GET of
 * `data?store=<id, or empty for every store view>` with the read deadline and the caller's signal, its answer parsed
 * before anyone sees it.
 */
import { READ_DEADLINE_MS, type JsonClient } from '@hryvinskyi/admin-ui';
import type { OverviewData } from '../domain/model.ts';
import { parseOverviewData } from './overview-data.ts';

export interface OverviewEndpoints {
    read(store: number | null, signal: AbortSignal): Promise<OverviewData>;
}

export function httpOverviewEndpoints(client: JsonClient, dataUrl: string): OverviewEndpoints {
    return Object.freeze({
        async read(store: number | null, signal: AbortSignal): Promise<OverviewData> {
            const answer = await client.get(dataUrl, {
                signal,
                timeoutMs: READ_DEADLINE_MS,
                query: { store: store === null ? '' : String(store) },
            });
            return parseOverviewData(answer, '$');
        },
    });
}
