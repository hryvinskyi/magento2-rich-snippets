/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the snippet editor needs from outside: the catalogs it reads, the placement count, the expression builder the
 * Pages card mounts for its conditions, the draft's preview and save, and the few things of the browser it uses — a
 * timer, fresh idempotency keys, the clipboard and the page's address. The editor's composition root builds the real
 * ones; tests give fakes.
 *
 * Every call takes the caller's signal. A store view is the id of one, or null for the default store view.
 */
import type {
    Draft,
    DraftProblem,
    FieldCatalog,
    IncludeCandidate,
    Placement,
    PlacementCount,
    PreviewResult,
    Sample,
} from '../domain/model.ts';

export interface EditorEndpoints {
    /** The field catalog, with each field's value on the sample page (`kind:id`), or none for no page. */
    fields(sample: string | null, store: number | null, signal: AbortSignal): Promise<FieldCatalog>;
    /**
     * Pages of the kinds given (every kind for none), to show values for: those the conditions (the expression
     * builder's text, empty for none) reach first.
     */
    samples(kinds: readonly string[], conditions: string, store: number | null, signal: AbortSignal): Promise<readonly Sample[]>;
    /** The snippets the snippet with this id may include; null for one not saved yet. */
    includeCandidates(id: number | null, signal: AbortSignal): Promise<readonly IncludeCandidate[]>;
    /** The expression builder's configuration for the kinds picked. */
    builderConfig(kinds: readonly string[], store: number | null, signal: AbortSignal): Promise<Readonly<Record<string, unknown>>>;
    placementCount(placement: Placement, store: number | null, signal: AbortSignal): Promise<PlacementCount>;
}

/** An expression builder mounted into a host element. */
export interface ConditionsHandle {
    /** Shows a condition tree changed from outside, without reporting it back as a change. */
    setValue(value: string): void;
    destroy(): void;
}

export interface ConditionsWidget {
    /** Mounts the builder; its configuration is fixed until it is destroyed. */
    mount(
        host: HTMLElement,
        options: { readonly value: string; readonly config: Readonly<Record<string, unknown>>; readonly label: string },
        onChange: (value: string) => void,
    ): ConditionsHandle;
}

/** Loads the expression builder once it is needed. */
export interface ConditionsWidgets {
    load(): Promise<ConditionsWidget>;
}

/** What a save answered: the snippet as stored, the problems that stopped it, or someone else's save that came first. */
export type SaveAnswer =
    | { readonly kind: 'saved'; readonly draft: Draft }
    | { readonly kind: 'refused'; readonly problems: readonly DraftProblem[] }
    | { readonly kind: 'conflict'; readonly message: string };

/** The draft's preview and its save. */
export interface DraftGateway {
    /** The draft on the sample page (`kind:id`), read in the store view (null for the default one). */
    preview(draft: Draft, sample: Sample, store: number | null, signal: AbortSignal): Promise<PreviewResult>;
    /**
     * Saves the draft made from the version saved at `updatedAt` (null for a new snippet); a new snippet carries the
     * idempotency key of the admin's intent to create it.
     */
    save(draft: Draft, updatedAt: string | null, idempotencyKey: string | null, signal: AbortSignal): Promise<SaveAnswer>;
}

/** Runs something later; answers the function that cancels it. */
export interface Scheduler {
    after(milliseconds: number, run: () => void): () => void;
}

/** A fresh key for each intent to create something that must be created once. */
export interface IdempotencyKeys {
    next(): string;
}

export interface Clipboard {
    write(text: string): Promise<void>;
}

/** The page's address. */
export interface Navigation {
    /** Opens another address. */
    go(url: string): void;
    /** Shows another address for this page without loading it. */
    replace(url: string): void;
    /** Loads this page again. */
    reload(): void;
}
