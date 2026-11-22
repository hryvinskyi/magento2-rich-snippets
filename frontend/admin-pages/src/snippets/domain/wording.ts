/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The two ports the page's sentences are put together through: the page's phrases (the page's translator fills
 * them) and the dates of the server, written for the admin's locale. Sentences that nest one phrase inside another
 * ask these; the pure modules that build them hold no words and read no clock.
 */
import type { PhraseParam } from '@hryvinskyi/admin-ui';

/** The page's phrases: one by key, a plural one by count, a number, and names joined the locale's way. */
export interface Words {
    t(key: string, ...params: readonly PhraseParam[]): string;
    tn(key: string, count: number, ...params: readonly PhraseParam[]): string;
    n(value: number): string;
    list(values: readonly string[]): string;
}

/**
 * A date as the server writes it (`Y-m-d H:i:s` in UTC, or ISO 8601), in the admin's words; the text itself when it
 * is no date.
 */
export interface Days {
    /** Day and short month: "12 Nov". */
    short(at: string): string;
    /** Day and month: "12 November". */
    long(at: string): string;
    /** Day, month and year: "13 November 2026". */
    full(at: string): string;
}

/** Parts of one line, set apart by a middle dot as the design writes them. */
export const joinParts = (parts: readonly string[]): string => parts.filter((part) => part !== '').join(' · ');

/** A sentence that starts a line: its first letter upper case. */
export const capitalised = (text: string): string => text.charAt(0).toUpperCase() + text.slice(1);
