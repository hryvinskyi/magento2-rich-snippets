/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview for tests: drawn from the page document the server prints, inside the providers the page gives it,
 * with every phrase of the page's contract (`Test/_contract/admin-pages/overview-phrases.json`) worded as its own key
 * followed by its placeholders, a translator that records each phrase it lacks, an admin in British English, and
 * endpoints a test scripts.
 */
import { readFileSync } from 'node:fs';
import { render, type RenderResult } from '@testing-library/preact';
import { createTranslator, KitProvider, type Kit, type Logger } from '@hryvinskyi/admin-ui';
import type { OverviewEndpoints } from '../overview/adapters/overview-endpoints.ts';
import { parseOverviewDocument } from '../overview/adapters/overview-document.ts';
import { OverviewIsland } from '../overview/ui/OverviewIsland.tsx';
import { pageDocument, type DocumentOptions } from './fixtures.ts';

const PHRASES = new URL('../../../../Test/_contract/admin-pages/overview-phrases.json', import.meta.url);

export const LOCALE = 'en_GB';

/** The page's phrase keys, as its contract lists them. */
export function contractKeys(): readonly string[] {
    const value: unknown = JSON.parse(readFileSync(PHRASES, 'utf8'));
    const keys: unknown = typeof value === 'object' && value !== null ? Reflect.get(value, 'keys') : undefined;
    if (!Array.isArray(keys) || !keys.every((key: unknown): key is string => typeof key === 'string')) {
        throw new Error('overview-phrases.json: expected {"keys": [string, …]}');
    }
    return keys;
}

/** A phrase as tests word it: its key, then its placeholders, so a filled value shows after the key. */
export const wording = (key: string): string => `${key} %1`;

/** Every phrase of the contract, worded as tests word them. */
export const contractPhrases = (): Readonly<Record<string, string>> =>
    Object.fromEntries(contractKeys().map((key) => [key, wording(key)]));

/** The kit as a test provides it: its retry action worded, its other phrases read as their keys. */
export function testKit(): Kit {
    return {
        locale: LOCALE,
        translator: createTranslator({
            locale: LOCALE,
            phrases: new Map([['kit.state.retry', 'Try again']]),
            onMissing: () => undefined,
        }),
    };
}

/** Endpoints that fail the test when called, for a state that must not ask the server anything. */
export const silentEndpoints: OverviewEndpoints = Object.freeze({
    read: () => Promise.reject(new Error('The data endpoint was not to be called')),
});

export interface DrawOptions extends DocumentOptions {
    readonly endpoints?: OverviewEndpoints;
    /** Told every phrase the page asked for that the document lacked. */
    readonly missing?: Set<string>;
    readonly logger?: Logger;
}

/** The Overview drawn from a state's data, as the page prints it. */
export function drawOverview(data: unknown, options: DrawOptions = {}): RenderResult {
    const missing = options.missing ?? new Set<string>();
    const logger: Logger = options.logger ?? {
        error: (message: string): void => {
            const key = /no phrase "([^"]+)"/.exec(message)?.[1];
            if (key !== undefined) {
                missing.add(key);
            }
        },
    };
    const document = parseOverviewDocument(pageDocument(data, contractPhrases(), options));
    return render(
        <KitProvider kit={testKit()}>
            <OverviewIsland document={document} endpoints={options.endpoints ?? silentEndpoints} logger={logger} />
        </KitProvider>,
    );
}
