/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The pages behind a finding saved by the browser: a GET of the download endpoint (`?key=<key>&store=<id, or empty
 * for every store view>`) with the admin's request rules (see `file-download.ts`), its CSV handed to the browser to
 * save under the name the server gives in `Content-Disposition`.
 *
 * Besides the failures of every download, a success that carries no file name is not the file: the endpoint answers
 * every refusal with a status of its own, so such an answer is the admin's notice that the session ended.
 */
import { RequestError } from '@hryvinskyi/admin-ui';
import { fetchFile, type DownloadFetch, type SaveFile } from '../../shared/file-download.ts';
import type { FindingsDownloads } from '../application/ports.ts';
import type { Finding } from '../domain/model.ts';

export interface FindingsDownloadOptions {
    /** The download endpoint's address. */
    readonly url: string;
    /** What a relative address resolves against (the page's own). */
    readonly baseUrl: string;
    readonly fetch: DownloadFetch;
    readonly save: SaveFile;
    readonly timeoutMs?: number;
}

export function httpFindingsDownloads(options: FindingsDownloadOptions): FindingsDownloads {
    return Object.freeze({
        async download(finding: Finding, store: number | null, signal: AbortSignal): Promise<void> {
            const file = await fetchFile({
                url: options.url,
                baseUrl: options.baseUrl,
                fetch: options.fetch,
                query: { key: finding.key, store: store === null ? '' : String(store) },
                accept: 'text/csv',
                timeoutMs: options.timeoutMs,
            }, signal);
            if (file.fileName === null) {
                throw new RequestError('expired');
            }
            options.save(file.fileName, file.text);
        },
    });
}
