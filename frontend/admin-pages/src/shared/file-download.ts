/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A file the admin asks a page's endpoint for: a GET with the admin's request rules — `isAjax`, a deadline and the
 * caller's signal — whose answer is read as text, with the name the server gives it in `Content-Disposition`.
 *
 * Failures are the kit's request failures, decided before the page looks at the file: an answer that came through a
 * redirect means the session ended, a 400 is refused with the reason the server gave as `error`, a 403 is forbidden,
 * and any other status that is not a success is the server's failure. A success may still be no file — the admin's
 * notice that the session ended, say — so the page decides what a success without a name, or with a body it cannot
 * use, stands for.
 *
 * Each page carries its own copy of this module: it is listed among the directories bundled into every page.
 */
import { isRecord, RequestError, type HttpResponse } from '@hryvinskyi/admin-ui';

/** What a download reads of a fetch response: the kit's part, and the headers. */
export interface DownloadResponse extends HttpResponse {
    readonly headers: { get(name: string): string | null };
}

export type DownloadFetch = (url: string, init: RequestInit) => Promise<DownloadResponse>;

/** Hands a file's text to the browser to save under its name. */
export type SaveFile = (fileName: string, text: string) => void;

export interface FileRequest {
    /** The endpoint's address. */
    readonly url: string;
    /** What a relative address resolves against (the page's own). */
    readonly baseUrl: string;
    readonly fetch: DownloadFetch;
    /** Query parameters added to the address. */
    readonly query?: Readonly<Record<string, string>> | undefined;
    /** The kind of file asked for (`Accept`); any when left out. */
    readonly accept?: string | undefined;
    readonly timeoutMs?: number | undefined;
}

/** A successful answer: its text, and the name the server gave the file, null when it gave none. */
export interface FetchedFile {
    readonly fileName: string | null;
    readonly text: string;
}

/** How long a file may take when the caller names no deadline: the server builds the whole file first. */
export const FILE_DEADLINE_MS = 30_000;

const FILE_NAME = /filename="([^"]+)"/;

/** The reason a 400 answer gives as `error`; null when its body gives none. */
function rejectionReason(text: string): string | null {
    let body: unknown;
    try {
        body = JSON.parse(text);
    } catch {
        return null;
    }
    const error = isRecord(body) ? body['error'] : null;
    return typeof error === 'string' && error !== '' ? error : null;
}

/** The failure an answer stands for, or nothing when it is a success. */
function refusalOf(response: DownloadResponse, text: string): RequestError | null {
    if (response.redirected) {
        return new RequestError('expired');
    }
    if (response.status === 400) {
        return new RequestError('rejected', rejectionReason(text));
    }
    if (response.status === 403) {
        return new RequestError('forbidden');
    }
    return response.status < 200 || response.status > 299 ? new RequestError('server') : null;
}

/** Fetches a file; throws the request failure an answer stands for, or what aborted it when the caller did. */
export async function fetchFile(request: FileRequest, signal: AbortSignal): Promise<FetchedFile> {
    const target = new URL(request.url, request.baseUrl);
    for (const [name, value] of Object.entries(request.query ?? {})) {
        target.searchParams.set(name, value);
    }
    target.searchParams.set('isAjax', 'true');
    const deadline = AbortSignal.timeout(request.timeoutMs ?? FILE_DEADLINE_MS);
    let response: DownloadResponse;
    let text: string;
    try {
        response = await request.fetch(target.href, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: request.accept ?? '*/*', 'X-Requested-With': 'XMLHttpRequest' },
            signal: AbortSignal.any([signal, deadline]),
        });
        text = await response.text();
    } catch (error) {
        if (signal.aborted) {
            throw error;
        }
        throw new RequestError(deadline.aborted ? 'timeout' : 'network', null, { cause: error });
    }
    const refused = refusalOf(response, text);
    if (refused !== null) {
        throw refused;
    }
    return { fileName: FILE_NAME.exec(response.headers.get('Content-Disposition') ?? '')?.[1] ?? null, text };
}
