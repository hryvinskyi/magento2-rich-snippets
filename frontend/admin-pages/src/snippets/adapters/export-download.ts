/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * An export saved by the browser: a GET of the export endpoint (`?id=<id>` for one snippet, nothing for every one),
 * with the admin's request rules (see `file-download.ts`), its body handed to the browser to save under the name the
 * server gives in `Content-Disposition`. The body must be an export of this module (its header and its `snippets`
 * list), whose snippets are counted for the page's message.
 *
 * Failures are the kit's request failures: besides those of every download, a success whose body is not JSON means
 * the session ended, and a body that is no export, or a file without a name, is the server's failure.
 */
import { at, isRecord, list, RequestError } from '@hryvinskyi/admin-ui';
import { fetchFile, type DownloadFetch, type SaveFile } from '../../shared/file-download.ts';
import type { ExportDownloads } from '../application/ports.ts';
import type { ExportedFile } from '../domain/model.ts';

export type { DownloadFetch, DownloadResponse, SaveFile } from '../../shared/file-download.ts';

export interface ExportDownloadOptions {
    /** The export endpoint's address. */
    readonly url: string;
    /** What a relative address resolves against (the page's own). */
    readonly baseUrl: string;
    readonly fetch: DownloadFetch;
    readonly save: SaveFile;
    readonly timeoutMs?: number;
}

const HEADER = 'hryvinskyi_rich_snippets';

function readJson(text: string): { readonly ok: true; readonly value: unknown } | { readonly ok: false } {
    try {
        const value: unknown = JSON.parse(text);
        return { ok: true, value };
    } catch {
        return { ok: false };
    }
}

/** How many snippets an export holds; throws the server's failure for a body that is no export. */
function countOf(value: unknown): number {
    if (!isRecord(value) || !isRecord(value[HEADER])) {
        throw new RequestError('server');
    }
    return list(value['snippets'], at('$', 'snippets')).length;
}

export function httpExportDownloads(options: ExportDownloadOptions): ExportDownloads {
    const download = async (id: number | null, signal: AbortSignal): Promise<ExportedFile> => {
        const file = await fetchFile({
            url: options.url,
            baseUrl: options.baseUrl,
            fetch: options.fetch,
            query: id === null ? {} : { id: String(id) },
            accept: 'application/json',
            timeoutMs: options.timeoutMs,
        }, signal);
        const body = readJson(file.text);
        if (!body.ok) {
            throw new RequestError('expired');
        }
        const count = countOf(body.value);
        if (file.fileName === null) {
            throw new RequestError('server');
        }
        options.save(file.fileName, file.text);
        return { fileName: file.fileName, count };
    };
    return Object.freeze({
        one: (id: number, signal: AbortSignal): Promise<ExportedFile> => download(id, signal),
        all: (signal: AbortSignal): Promise<ExportedFile> => download(null, signal),
    });
}
