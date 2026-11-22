/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Snippets page needs from outside: its endpoints, the browser saving an export, a fresh idempotency key for
 * each write that creates something, and a file the admin chose. The page's composition root builds the real ones;
 * tests give fakes.
 *
 * Every call takes the caller's signal; a store view is the id of one, or null for every store view, and is sent with
 * each write so the list in its answer is the one on screen.
 */
import type { ExportedFile, ImportAnswer, ImportChoice, ImportPreview, Listing, WriteAnswer } from '../domain/model.ts';

export interface SnippetsEndpoints {
    list(store: number | null, signal: AbortSignal): Promise<Listing>;
    /** Switches a snippet into the state wanted, never "the other one", so a repeated request changes nothing more. */
    setActive(id: number, active: boolean, store: number | null, signal: AbortSignal): Promise<WriteAnswer>;
    /** Adds a switched-off copy; a request repeated with the same key copies nothing more. */
    duplicate(id: number, idempotencyKey: string, store: number | null, signal: AbortSignal): Promise<WriteAnswer>;
    remove(id: number, store: number | null, signal: AbortSignal): Promise<WriteAnswer>;
    /** What importing the file's text would do, changing nothing. */
    previewImport(file: string, signal: AbortSignal): Promise<ImportPreview>;
    /** Imports the file's text as chosen, all or nothing; a request repeated with the same key imports nothing more. */
    commitImport(
        file: string,
        choices: ReadonlyMap<string, ImportChoice>,
        idempotencyKey: string,
        store: number | null,
        signal: AbortSignal,
    ): Promise<ImportAnswer>;
}

/** Fetches an export and hands it to the browser to save under the server's file name. */
export interface ExportDownloads {
    one(id: number, signal: AbortSignal): Promise<ExportedFile>;
    all(signal: AbortSignal): Promise<ExportedFile>;
}

/** A key generated once per intent of the admin, sent with every attempt of the write it belongs to. */
export interface IdempotencyKeys {
    next(): string;
}

/** A file the admin chose: its name and size, and its text once read. */
export interface ChosenFile {
    readonly name: string;
    readonly size: number;
    text(): Promise<string>;
}
