/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page for tests: drawn from the page document the server prints, inside the providers the page gives it,
 * with every phrase of the page's contract (`Test/_contract/admin-pages/test-phrases.json`) worded as its own key
 * followed by its first three placeholders, an admin in British English, and ports a test scripts: each call to an
 * endpoint or a download waits until the test answers it — in the server's shape, read by the page's own readers —
 * or refuses it; the clock runs a task only when the test moves it on; the page's address records what it is told.
 */
import { readFileSync } from 'node:fs';
import { render, type RenderResult } from '@testing-library/preact';
import { isRecord, KitProvider, type Logger } from '@hryvinskyi/admin-ui';
import { parseTestDocument } from '../test-page/adapters/test-document.ts';
import {
    parseCheckStatus,
    parseFindingItems,
    parseFindingsTable,
    parsePageTest,
    parseSearchAnswer,
} from '../test-page/adapters/test-data.ts';
import type { FindingsDownloads, Scheduler, TestEndpoints } from '../test-page/application/ports.ts';
import { TestIsland } from '../test-page/ui/TestIsland.tsx';
import type { TestPorts } from '../test-page/ui/TestPage.tsx';
import { testKit } from './harness.tsx';
import { testDocument, type TestDocumentOptions } from './test-page-fixtures.ts';

const PHRASES = new URL('../../../../Test/_contract/admin-pages/test-phrases.json', import.meta.url);

/** The page's phrase keys, as its contract lists them. */
export function testKeys(): readonly string[] {
    const value: unknown = JSON.parse(readFileSync(PHRASES, 'utf8'));
    const keys: unknown = isRecord(value) ? value['keys'] : undefined;
    if (!Array.isArray(keys) || !keys.every((key: unknown): key is string => typeof key === 'string')) {
        throw new Error('test-phrases.json: expected {"keys": [string, …]}');
    }
    return keys;
}

/** A phrase as these tests word it: its key, then its placeholders, so filled values show after the key. */
export const testWording = (key: string): string => `${key} %1 %2 %3`;

/** Every phrase of the contract, worded as these tests word them. */
export const testPhrases = (): Readonly<Record<string, string>> =>
    Object.fromEntries(testKeys().map((key) => [key, testWording(key)]));

export type TestMethod = 'search' | 'run' | 'status' | 'requestCheck' | 'findings' | 'findingItems' | 'download';

export interface TestCall {
    readonly method: TestMethod;
    readonly args: readonly unknown[];
    readonly signal: AbortSignal;
}

/** A clock the test moves on by hand. */
export interface ManualClock extends Scheduler {
    /** The tasks waiting, with how long each was to wait. */
    readonly waiting: () => readonly number[];
    /** Runs every task waiting now, in the order they were set. */
    run(): void;
}

export function manualClock(): ManualClock {
    let tasks: { readonly ms: number; readonly task: () => void; cancelled: boolean }[] = [];
    return {
        after(ms: number, task: () => void): () => void {
            const entry = { ms, task, cancelled: false };
            tasks.push(entry);
            return (): void => {
                entry.cancelled = true;
            };
        },
        waiting: () => tasks.filter((entry) => !entry.cancelled).map((entry) => entry.ms),
        run(): void {
            const due = tasks.filter((entry) => !entry.cancelled);
            tasks = [];
            due.forEach((entry) => entry.task());
        },
    };
}

export interface ScriptedTest {
    readonly ports: TestPorts;
    readonly clock: ManualClock;
    /** Every call made, in order. */
    readonly calls: TestCall[];
    /** Every address the page showed, in order. */
    readonly addresses: ReadonlyMap<string, string>[];
    readonly errors: string[];
    callsTo(method: TestMethod): readonly (readonly unknown[])[];
    /** Answers the oldest call to `method` still waiting, with the value the server would answer. */
    answer(method: TestMethod, value: unknown): void;
    refuse(method: TestMethod, error: unknown): void;
    waiting(method: TestMethod): number;
}

interface Waiter {
    resolve(value: unknown): void;
    reject(error: unknown): void;
}

export function scriptedTest(): ScriptedTest {
    const calls: TestCall[] = [];
    const errors: string[] = [];
    const addresses: ReadonlyMap<string, string>[] = [];
    const queues = new Map<TestMethod, Waiter[]>();
    const queue = (method: TestMethod): Waiter[] => {
        const known = queues.get(method);
        if (known !== undefined) {
            return known;
        }
        const created: Waiter[] = [];
        queues.set(method, created);
        return created;
    };
    function wait<T>(method: TestMethod, args: readonly unknown[], signal: AbortSignal, read: (value: unknown) => T): Promise<T> {
        calls.push({ method, args, signal });
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
    const endpoints: TestEndpoints = {
        search: (query, store, signal) => wait('search', [query, store], signal, (value) => parseSearchAnswer(value, '$')),
        run: (page, store, signal) => wait('run', [page.ref ?? page.path, store], signal, (value) => parsePageTest(value, '$')),
        status: (signal) => wait('status', [], signal, (value) => parseCheckStatus(value, '$')),
        requestCheck: (signal) => wait('requestCheck', [], signal, (value) => parseCheckStatus(value, '$')),
        findings: (store, signal) => wait('findings', [store], signal, (value) => parseFindingsTable(value, '$')),
        findingItems: (key, store, page, signal) =>
            wait('findingItems', [key, store, page], signal, (value) => parseFindingItems(value, '$')),
    };
    const downloads: FindingsDownloads = {
        download: (finding, store, signal) => wait('download', [finding.key, store], signal, () => undefined),
    };
    const take = (method: TestMethod): Waiter => {
        const waiter = queue(method).shift();
        if (waiter === undefined) {
            throw new Error(`No call to ${method} is waiting`);
        }
        return waiter;
    };
    const logger: Logger = { error: (message: string): void => void errors.push(message) };
    const clock = manualClock();
    return {
        ports: { endpoints, downloads, scheduler: clock, address: { show: (params) => void addresses.push(params) }, logger },
        clock,
        calls,
        addresses,
        errors,
        callsTo: (method) => calls.filter((call) => call.method === method).map((call) => call.args),
        answer: (method, value) => take(method).resolve(value),
        refuse: (method, error) => take(method).reject(error),
        waiting: (method) => queue(method).length,
    };
}

export interface DrawTestOptions extends TestDocumentOptions {
    readonly script?: ScriptedTest;
}

export interface DrawnTest extends RenderResult {
    readonly script: ScriptedTest;
}

/** The Test page drawn from its first data, as the page prints it. */
export function drawTest(data: unknown, options: DrawTestOptions = {}): DrawnTest {
    const script = options.script ?? scriptedTest();
    const document = parseTestDocument(testDocument(data, testPhrases(), options));
    const result = render(
        <KitProvider kit={testKit()}>
            <TestIsland document={document} ports={script.ports} />
        </KitProvider>,
    );
    return { ...result, script };
}
