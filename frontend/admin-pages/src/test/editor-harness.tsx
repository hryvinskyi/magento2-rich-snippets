/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor for tests: drawn from the page document the server prints, inside the providers the page gives
 * it, with every phrase of the editor's contract (`Test/_contract/admin-pages/editor-phrases.json`) worded as its own
 * key followed by its first three placeholders, and fake ports. The endpoints answer at once with the catalog
 * contract, read by the editor's own readers, and record each call; the expression builder records what it was
 * mounted with, the values it was shown and whether it was destroyed, and a test reports a change from it. The
 * draft's preview and save are held until the test answers them (each request records its signal, so an abort is
 * seen); the timer runs what is due only when the test moves it on; keys count up; the clipboard and the address
 * record what they were given.
 */
import { readFileSync } from 'node:fs';
import { render, type RenderResult } from '@testing-library/preact';
import { isRecord, KitProvider, type Logger } from '@hryvinskyi/admin-ui';
import { parseEditorDocument } from '../editor/adapters/editor-document.ts';
import { parseBuilderConfig, parseCandidates, parseFieldCatalog, parsePlacementCount, parseSamples } from '../editor/adapters/editor-data.ts';
import { createEditorStore, type EditorStore } from '../editor/application/editor-store.ts';
import { parsePreview, parseSaveAnswer } from '../editor/adapters/draft-endpoints.ts';
import type {
    Clipboard,
    ConditionsWidgets,
    DraftGateway,
    EditorEndpoints,
    IdempotencyKeys,
    Navigation,
    SaveAnswer,
    Scheduler,
} from '../editor/application/ports.ts';
import type { Draft, PreviewResult, Sample } from '../editor/domain/model.ts';
import { EditorIsland } from '../editor/ui/EditorIsland.tsx';
import type { EditorSlots } from '../editor/ui/EditorPage.tsx';
import { EditorWorkbench } from '../editor/ui/EditorWorkbench.tsx';
import { testKit } from './harness.tsx';
import { catalogAnswer, editorDocument, previewAnswer, saveAnswer, type EditorDocumentOptions } from './editor-fixtures.ts';

const PHRASES = new URL('../../../../Test/_contract/admin-pages/editor-phrases.json', import.meta.url);

export function editorKeys(): readonly string[] {
    const value: unknown = JSON.parse(readFileSync(PHRASES, 'utf8'));
    const keys: unknown = isRecord(value) ? value['keys'] : undefined;
    if (!Array.isArray(keys) || !keys.every((key: unknown): key is string => typeof key === 'string')) {
        throw new Error('editor-phrases.json: expected {"keys": [string, …]}');
    }
    return keys;
}

/** A phrase as these tests word it: its key, then its placeholders, so filled values show after the key. */
export const editorWording = (key: string): string => `${key} %1 %2 %3`;

export const editorPhrases = (): Readonly<Record<string, string>> =>
    Object.fromEntries(editorKeys().map((key) => [key, editorWording(key)]));

export interface EndpointCall {
    readonly method: keyof EditorEndpoints;
    readonly args: readonly unknown[];
}

export interface FakeBuilder {
    readonly mounts: { readonly value: string; readonly config: Readonly<Record<string, unknown>>; readonly label: string }[];
    readonly shown: string[];
    destroyed: number;
    /** Reports a change from the builder last mounted. */
    change(value: string): void;
}

/** A request held until the test answers it. */
export interface Held<A, T> {
    readonly args: A;
    readonly signal: AbortSignal;
    answer(value: T): void;
    fail(error: unknown): void;
}

export interface FakeDrafts extends DraftGateway {
    readonly previews: Held<{ readonly draft: Draft; readonly sample: Sample; readonly store: number | null }, PreviewResult>[];
    readonly saves: Held<{ readonly draft: Draft; readonly updatedAt: string | null; readonly key: string | null }, SaveAnswer>[];
}

export interface FakeScheduler extends Scheduler {
    /** Moves the clock on, running what has come due. */
    advance(milliseconds: number): void;
    /** How many runs are waiting. */
    pending(): number;
}

export interface FakePorts {
    readonly endpoints: EditorEndpoints;
    readonly widgets: ConditionsWidgets;
    readonly drafts: FakeDrafts;
    readonly scheduler: FakeScheduler;
    readonly keys: IdempotencyKeys;
    readonly clipboard: Clipboard & { readonly written: string[] };
    readonly navigation: Navigation & { readonly visits: string[] };
    readonly logger: Logger;
    readonly calls: EndpointCall[];
    readonly errors: string[];
    readonly builder: FakeBuilder;
}

function held<A, T>(list: Held<A, T>[], args: A, signal: AbortSignal): Promise<T> {
    return new Promise<T>((resolve, reject) => {
        signal.addEventListener('abort', () => reject(signal.reason), { once: true });
        list.push({ args, signal, answer: resolve, fail: reject });
    });
}

export function fakeDrafts(): FakeDrafts {
    const previews: FakeDrafts['previews'] = [];
    const saves: FakeDrafts['saves'] = [];
    return {
        previews,
        saves,
        preview: (draft, sample, store, signal) => held(previews, { draft, sample, store }, signal),
        save: (draft, updatedAt, key, signal) => held(saves, { draft, updatedAt, key }, signal),
    };
}

export function fakeScheduler(): FakeScheduler {
    let now = 0;
    const waiting: { readonly at: number; readonly run: () => void }[] = [];
    return {
        after(milliseconds, run) {
            const entry = { at: now + milliseconds, run };
            waiting.push(entry);
            return () => {
                const index = waiting.indexOf(entry);
                if (index >= 0) {
                    waiting.splice(index, 1);
                }
            };
        },
        advance(milliseconds) {
            now += milliseconds;
            const due = waiting.filter((entry) => entry.at <= now);
            due.forEach((entry) => waiting.splice(waiting.indexOf(entry), 1));
            due.forEach((entry) => entry.run());
        },
        pending: () => waiting.length,
    };
}

/** A preview answer of the contract, read for the sample asked for. */
export const contractPreview = (state: 'output' | 'conditionsNotMet' | 'noType', overrides: Readonly<Record<string, unknown>> = {}): (sample: Sample) => PreviewResult =>
    (sample) => parsePreview({ ...previewAnswer(state), ...overrides }, '$', sample);

/** A save answer of the contract. */
export const contractSave = (outcome: 'saved' | 'refused' | 'conflict'): SaveAnswer => parseSaveAnswer(saveAnswer(outcome), '$');

export function fakePorts(): FakePorts {
    const calls: EndpointCall[] = [];
    const errors: string[] = [];
    const record = <T,>(method: keyof EditorEndpoints, args: readonly unknown[], value: T): Promise<T> => {
        calls.push({ method, args });
        return Promise.resolve(value);
    };
    let onChange: (value: string) => void = () => undefined;
    const builder: FakeBuilder = {
        mounts: [],
        shown: [],
        destroyed: 0,
        change: (value) => onChange(value),
    };
    const endpoints: EditorEndpoints = {
        fields: (sample, store) => record('fields', [sample, store], parseFieldCatalog(catalogAnswer('fields'), '$')),
        samples: (kinds, conditions, store) => record('samples', [kinds, conditions, store], parseSamples(catalogAnswer('samples'), '$')),
        includeCandidates: (id) => record('includeCandidates', [id], parseCandidates(catalogAnswer('includeCandidates'), '$')),
        builderConfig: (kinds, store) => record('builderConfig', [kinds, store], parseBuilderConfig(catalogAnswer('builderConfig'), '$')),
        placementCount: (placement, store) => record('placementCount', [placement, store], parsePlacementCount(catalogAnswer('placementCount'), '$')),
    };
    const widgets: ConditionsWidgets = {
        load: () => Promise.resolve({
            mount: (host, options, change) => {
                builder.mounts.push(options);
                onChange = change;
                host.setAttribute('data-mounted', 'true');
                return {
                    setValue: (value: string) => void builder.shown.push(value),
                    destroy: () => {
                        builder.destroyed++;
                    },
                };
            },
        }),
    };
    const written: string[] = [];
    const visits: string[] = [];
    let key = 0;
    return {
        endpoints,
        widgets,
        drafts: fakeDrafts(),
        scheduler: fakeScheduler(),
        keys: { next: () => `key-${String(++key)}` },
        clipboard: { written, write: (text) => Promise.resolve(void written.push(text)) },
        navigation: {
            visits,
            go: (url) => void visits.push(`go ${url}`),
            replace: (url) => void visits.push(`replace ${url}`),
            reload: () => void visits.push('reload'),
        },
        logger: { error: (message: string): void => void errors.push(message) },
        calls,
        errors,
        builder,
    };
}

export interface DrawnEditor extends RenderResult {
    readonly ports: FakePorts;
    readonly store: EditorStore;
}

export interface DrawEditorOptions extends EditorDocumentOptions {
    readonly slots?: EditorSlots;
}

/** The editor drawn from its first data, as the page prints it. */
export function drawEditor(data: unknown, options: DrawEditorOptions = {}): DrawnEditor {
    const ports = fakePorts();
    const document = parseEditorDocument(editorDocument(data, editorPhrases(), options));
    const store = createEditorStore(document.data.snippet);
    const result = render(
        <KitProvider kit={testKit()}>
            <EditorIsland document={document} store={store} ports={ports} slots={options.slots} />
        </KitProvider>,
    );
    return { ...result, ports, store };
}

/** The whole editor — the page with the Code tab, the side column and the save — drawn from its first data. */
export function drawWorkbench(data: unknown, options: EditorDocumentOptions = {}): DrawnEditor {
    const ports = fakePorts();
    const document = parseEditorDocument(editorDocument(data, editorPhrases(), options));
    const store = createEditorStore(document.data.snippet);
    const result = render(
        <KitProvider kit={testKit()}>
            <EditorWorkbench document={document} store={store} ports={ports} />
        </KitProvider>,
    );
    return { ...result, ports, store };
}
