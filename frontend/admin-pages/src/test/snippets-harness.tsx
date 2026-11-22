/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page for tests: drawn from the page document the server prints, inside the providers the page gives
 * it, with every phrase of the page's contract (`Test/_contract/admin-pages/snippets-phrases.json`) worded as its own
 * key followed by its first three placeholders, an admin in British English with dates in UTC, and ports a test
 * scripts: each call to an endpoint or a download waits until the test answers it — in the server's shape, read by
 * the page's own readers — or refuses it.
 */
import { readFileSync } from 'node:fs';
import { render, type RenderResult } from '@testing-library/preact';
import { isRecord, KitProvider, type Logger } from '@hryvinskyi/admin-ui';
import { parseSnippetsDocument } from '../snippets/adapters/snippets-document.ts';
import { parseImportAnswer, parseImportPreview, parseListing, parseWriteAnswer } from '../snippets/adapters/snippets-data.ts';
import type { ExportDownloads, SnippetsEndpoints } from '../snippets/application/ports.ts';
import type { SnippetsPorts } from '../snippets/application/use-snippets.ts';
import type { ExportedFile, ImportChoice } from '../snippets/domain/model.ts';
import { SnippetsIsland } from '../snippets/ui/SnippetsIsland.tsx';
import { testKit } from './harness.tsx';
import { snippetsDocument, type SnippetsDocumentOptions } from './snippets-fixtures.ts';

const PHRASES = new URL('../../../../Test/_contract/admin-pages/snippets-phrases.json', import.meta.url);

/** The page's phrase keys, as its contract lists them. */
export function snippetsKeys(): readonly string[] {
    const value: unknown = JSON.parse(readFileSync(PHRASES, 'utf8'));
    const keys: unknown = isRecord(value) ? value['keys'] : undefined;
    if (!Array.isArray(keys) || !keys.every((key: unknown): key is string => typeof key === 'string')) {
        throw new Error('snippets-phrases.json: expected {"keys": [string, …]}');
    }
    return keys;
}

/** A phrase as these tests word it: its key, then its placeholders, so filled values show after the key. */
export const snippetsWording = (key: string): string => `${key} %1 %2 %3`;

/** Every phrase of the contract, worded as these tests word them. */
export const snippetsPhrases = (): Readonly<Record<string, string>> =>
    Object.fromEntries(snippetsKeys().map((key) => [key, snippetsWording(key)]));

export type PortMethod = 'list' | 'setActive' | 'duplicate' | 'remove' | 'previewImport' | 'commitImport' | 'one' | 'all';

export interface PortCall {
    readonly method: PortMethod;
    readonly args: readonly unknown[];
}

export interface ScriptedPorts {
    readonly ports: SnippetsPorts;
    /** Every call made, in order, without its abort signal. */
    readonly calls: PortCall[];
    /** What the page logged as a fault. */
    readonly errors: string[];
    /** The arguments of each call to one method, in order. */
    callsTo(method: PortMethod): readonly (readonly unknown[])[];
    /** Answers the oldest call to `method` still waiting, with the value the server would answer. */
    answer(method: PortMethod, value: unknown): void;
    /** Fails the oldest call to `method` still waiting. */
    refuse(method: PortMethod, error: unknown): void;
}

interface Waiter {
    resolve(value: unknown): void;
    reject(error: unknown): void;
}

function exportedFile(value: unknown): ExportedFile {
    if (!isRecord(value) || typeof value['fileName'] !== 'string' || typeof value['count'] !== 'number') {
        throw new Error('A download is answered with {"fileName", "count"}');
    }
    return { fileName: value['fileName'], count: value['count'] };
}

/** Ports whose every call waits for the test; idempotency keys count up from `key-1`. */
export function scriptedPorts(): ScriptedPorts {
    const calls: PortCall[] = [];
    const errors: string[] = [];
    const waiting = new Map<PortMethod, Waiter[]>();
    let issued = 0;
    const queue = (method: PortMethod): Waiter[] => {
        const known = waiting.get(method);
        if (known !== undefined) {
            return known;
        }
        const created: Waiter[] = [];
        waiting.set(method, created);
        return created;
    };
    function wait<T>(method: PortMethod, args: readonly unknown[], read: (value: unknown) => T): Promise<T> {
        calls.push({ method, args });
        return new Promise<T>((resolve, reject) => {
            queue(method).push({
                resolve: (value: unknown): void => {
                    try {
                        resolve(read(value));
                    } catch (error) {
                        reject(error);
                    }
                },
                reject,
            });
        });
    }
    const endpoints: SnippetsEndpoints = {
        list: (store) => wait('list', [store], (value) => parseListing(value, '$')),
        setActive: (id, active, store) => wait('setActive', [id, active, store], (value) => parseWriteAnswer(value, '$')),
        duplicate: (id, key, store) => wait('duplicate', [id, key, store], (value) => parseWriteAnswer(value, '$')),
        remove: (id, store) => wait('remove', [id, store], (value) => parseWriteAnswer(value, '$')),
        previewImport: (file) => wait('previewImport', [file], (value) => parseImportPreview(value, '$')),
        commitImport: (file, choices: ReadonlyMap<string, ImportChoice>, key, store) =>
            wait('commitImport', [file, Object.fromEntries(choices), key, store], (value) => parseImportAnswer(value, '$')),
    };
    const downloads: ExportDownloads = {
        one: (id) => wait('one', [id], exportedFile),
        all: () => wait('all', [], exportedFile),
    };
    const take = (method: PortMethod): Waiter => {
        const waiter = queue(method).shift();
        if (waiter === undefined) {
            throw new Error(`No call to ${method} is waiting`);
        }
        return waiter;
    };
    const logger: Logger = { error: (message: string): void => void errors.push(message) };
    return {
        ports: { endpoints, downloads, keys: { next: (): string => `key-${String(++issued)}` }, logger },
        calls,
        errors,
        callsTo: (method) => calls.filter((call) => call.method === method).map((call) => call.args),
        answer: (method, value) => take(method).resolve(value),
        refuse: (method, error) => take(method).reject(error),
    };
}

export interface DrawSnippetsOptions extends SnippetsDocumentOptions {
    readonly ports?: ScriptedPorts;
}

export interface DrawnSnippets extends RenderResult {
    readonly script: ScriptedPorts;
}

/** The Snippets page drawn from its first data, as the page prints it. */
export function drawSnippets(data: unknown, options: DrawSnippetsOptions = {}): DrawnSnippets {
    const script = options.ports ?? scriptedPorts();
    const document = parseSnippetsDocument(snippetsDocument(data, snippetsPhrases(), options));
    const result = render(
        <KitProvider kit={testKit()}>
            <SnippetsIsland document={document} ports={script.ports} timeZone="UTC" />
        </KitProvider>,
    );
    return { ...result, script };
}
