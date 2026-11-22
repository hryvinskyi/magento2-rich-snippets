/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Library page needs from outside: its endpoints, and a fresh idempotency key for each intent to add. The
 * page's composition root builds the real ones; tests give fakes. Every call takes the caller's signal.
 */
import type { AddAnswer, Library, TemplateAnswers } from '../domain/model.ts';

export interface LibraryEndpoints {
    list(signal: AbortSignal): Promise<Library>;
    /** The questions a template asks, with the answers this shop is offered. */
    suggestions(template: string, signal: AbortSignal): Promise<TemplateAnswers>;
    /** Adds a switched-off copy; a request repeated with the same key adds nothing more and answers the first add. */
    add(template: string, answers: ReadonlyMap<string, string>, idempotencyKey: string, signal: AbortSignal): Promise<AddAnswer>;
    /** Adds the recommended templates the shop has no copy of; repeated with the same key, it adds nothing more. */
    addRecommended(idempotencyKey: string, signal: AbortSignal): Promise<AddAnswer>;
}

/** A key generated once per intent of the admin, sent with every attempt of the add it belongs to. */
export interface IdempotencyKeys {
    next(): string;
}
