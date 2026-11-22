/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Test page needs from outside: its endpoints, the download of the pages behind a finding, a clock that
 * runs a task later, and the page's address. The page's composition root builds the real ones; tests give fakes.
 * Every call to the server takes the caller's signal.
 */
import type {
    CheckStatus,
    Finding,
    FindingItems,
    FindingsTable,
    PageTest,
    PickedPage,
    SearchAnswer,
} from '../domain/model.ts';

export interface TestEndpoints {
    /** The pages whose name holds a text, and the text when it is a storefront path; an empty text finds any page. */
    search(query: string, store: number, signal: AbortSignal): Promise<SearchAnswer>;
    /** Reads one storefront page as a visitor does and says what it carries. */
    run(page: PickedPage, store: number, signal: AbortSignal): Promise<PageTest>;
    status(signal: AbortSignal): Promise<CheckStatus>;
    /** Asks for a check now; asked while one is on its way, it asks for nothing more. Answers where it stands. */
    requestCheck(signal: AbortSignal): Promise<CheckStatus>;
    /** The findings of the last check, of one store view or of every one. */
    findings(store: number | null, signal: AbortSignal): Promise<FindingsTable>;
    /** One part of the pages behind a finding, counted from 1. */
    findingItems(key: string, store: number | null, page: number, signal: AbortSignal): Promise<FindingItems>;
}

/** Saves every page behind a finding as the file the server names. */
export interface FindingsDownloads {
    download(finding: Finding, store: number | null, signal: AbortSignal): Promise<void>;
}

/** Runs a task once, after a while; the function it answers cancels the task. */
export interface Scheduler {
    after(ms: number, task: () => void): () => void;
}

/** The page's own address: what it shows, written into its query so a reload opens the same. */
export interface PageAddress {
    show(params: ReadonlyMap<string, string>): void;
}
