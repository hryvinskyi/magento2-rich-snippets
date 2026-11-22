/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page for tests: drawn from the page document the server prints, inside the providers the page gives it,
 * with every phrase of the page's contract (`Test/_contract/admin-pages/library-phrases.json`) worded as its own key
 * followed by its first three placeholders, an admin in British English, and ports a test scripts: each call to an
 * endpoint waits until the test answers it — in the server's shape, read by the page's own readers — or refuses it.
 */
import { readFileSync } from 'node:fs';
import { render, type RenderResult } from '@testing-library/preact';
import { isRecord, KitProvider, type Logger } from '@hryvinskyi/admin-ui';
import { parseLibraryDocument } from '../library/adapters/library-document.ts';
import { parseAddAnswer, parseLibrary, parseTemplateAnswers } from '../library/adapters/library-data.ts';
import type { LibraryEndpoints } from '../library/application/ports.ts';
import type { LibraryPorts } from '../library/application/use-library.ts';
import { LibraryIsland } from '../library/ui/LibraryIsland.tsx';
import { testKit } from './harness.tsx';
import { libraryDocument, type LibraryDocumentOptions } from './library-fixtures.ts';

const PHRASES = new URL('../../../../Test/_contract/admin-pages/library-phrases.json', import.meta.url);

/** The page's phrase keys, as its contract lists them. */
export function libraryKeys(): readonly string[] {
    const value: unknown = JSON.parse(readFileSync(PHRASES, 'utf8'));
    const keys: unknown = isRecord(value) ? value['keys'] : undefined;
    if (!Array.isArray(keys) || !keys.every((key: unknown): key is string => typeof key === 'string')) {
        throw new Error('library-phrases.json: expected {"keys": [string, …]}');
    }
    return keys;
}

/** A phrase as these tests word it: its key, then its placeholders, so filled values show after the key. */
export const libraryWording = (key: string): string => `${key} %1 %2 %3`;

/** Every phrase of the contract, worded as these tests word them. */
export const libraryPhrases = (): Readonly<Record<string, string>> =>
    Object.fromEntries(libraryKeys().map((key) => [key, libraryWording(key)]));

export type LibraryMethod = 'list' | 'suggestions' | 'add' | 'addRecommended';

export interface LibraryCall {
    readonly method: LibraryMethod;
    readonly args: readonly unknown[];
}

export interface ScriptedLibrary {
    readonly ports: LibraryPorts;
    /** Every call made, in order, without its abort signal. */
    readonly calls: LibraryCall[];
    /** What the page logged as a fault. */
    readonly errors: string[];
    /** The arguments of each call to one method, in order. */
    callsTo(method: LibraryMethod): readonly (readonly unknown[])[];
    /** Answers the oldest call to `method` still waiting, with the value the server would answer. */
    answer(method: LibraryMethod, value: unknown): void;
    /** Fails the oldest call to `method` still waiting. */
    refuse(method: LibraryMethod, error: unknown): void;
    /** How many calls to `method` still wait for the test. */
    waiting(method: LibraryMethod): number;
}

interface Waiter {
    resolve(value: unknown): void;
    reject(error: unknown): void;
}

/** Ports whose every call waits for the test; idempotency keys count up from `key-1`. */
export function scriptedLibrary(): ScriptedLibrary {
    const calls: LibraryCall[] = [];
    const errors: string[] = [];
    const queues = new Map<LibraryMethod, Waiter[]>();
    let issued = 0;
    const queue = (method: LibraryMethod): Waiter[] => {
        const known = queues.get(method);
        if (known !== undefined) {
            return known;
        }
        const created: Waiter[] = [];
        queues.set(method, created);
        return created;
    };
    function wait<T>(method: LibraryMethod, args: readonly unknown[], read: (value: unknown) => T): Promise<T> {
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
    const endpoints: LibraryEndpoints = {
        list: () => wait('list', [], (value) => parseLibrary(value, '$')),
        suggestions: (template) => wait('suggestions', [template], (value) => parseTemplateAnswers(value, '$')),
        add: (template, answers, key) =>
            wait('add', [template, Object.fromEntries(answers), key], (value) => parseAddAnswer(value, '$')),
        addRecommended: (key) => wait('addRecommended', [key], (value) => parseAddAnswer(value, '$')),
    };
    const take = (method: LibraryMethod): Waiter => {
        const waiter = queue(method).shift();
        if (waiter === undefined) {
            throw new Error(`No call to ${method} is waiting`);
        }
        return waiter;
    };
    const logger: Logger = { error: (message: string): void => void errors.push(message) };
    return {
        ports: { endpoints, keys: { next: (): string => `key-${String(++issued)}` }, logger },
        calls,
        errors,
        callsTo: (method) => calls.filter((call) => call.method === method).map((call) => call.args),
        answer: (method, value) => take(method).resolve(value),
        refuse: (method, error) => take(method).reject(error),
        waiting: (method) => queue(method).length,
    };
}

export interface DrawLibraryOptions extends LibraryDocumentOptions {
    readonly ports?: ScriptedLibrary;
}

export interface DrawnLibrary extends RenderResult {
    readonly script: ScriptedLibrary;
}

/** The Library page drawn from its first data, as the page prints it. */
export function drawLibrary(data: unknown, options: DrawLibraryOptions = {}): DrawnLibrary {
    const script = options.ports ?? scriptedLibrary();
    const document = parseLibraryDocument(libraryDocument(data, libraryPhrases(), options));
    const result = render(
        <KitProvider kit={testKit()}>
            <LibraryIsland document={document} ports={script.ports} />
        </KitProvider>,
    );
    return { ...result, script };
}
