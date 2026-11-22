/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Whether a template text is JSON, and when it is not, the first thing wrong with it as a person would say it, with
 * the line it is on — so the Code tab can mark that line and say what to do.
 *
 * The text is read piece by piece and never gives up with "invalid JSON" alone. What is found, in the order met:
 * - **unfinished**: a line left half-written — a text in quotes whose closing quote is missing, a property name with
 *   no colon or a colon with no value before the line ends, or the text ending after a comma, a colon or an opening
 *   bracket. It is what a template looks like while it is being typed, so it is said as "not finished" rather than as
 *   a mistake;
 * - **missing-comma**: a value followed by another value with no comma between them; said on the line of the second,
 *   naming the line the first ended on;
 * - **trailing-comma**: a comma with nothing after it before the bracket closes;
 * - **unclosed**: an opening brace or bracket never closed (or closed by the other kind), said on its own line;
 * - **unexpected**: anything else that is not JSON at its place — a stray character, an unknown word, a text with an
 *   escape JSON does not have, a number written as JSON does not write it, a second value after the whole template.
 *
 * A valid text answers how many lines it has (as the editor numbers them) and how many fields its strings hold.
 */
import { fieldCount, lex, type Lexeme } from './template-lexer.ts';

export type TemplateProblem =
    | { readonly code: 'unfinished'; readonly line: number }
    | { readonly code: 'missing-comma'; readonly line: number; readonly after: number }
    | { readonly code: 'trailing-comma'; readonly line: number }
    | { readonly code: 'unclosed'; readonly line: number; readonly bracket: '{' | '[' }
    | { readonly code: 'unexpected'; readonly line: number };

export type ProblemCode = TemplateProblem['code'];

export type TemplateCheck =
    | { readonly kind: 'valid'; readonly lines: number; readonly fields: number }
    | { readonly kind: 'invalid'; readonly problem: TemplateProblem };

/** Thrown inside the reader to stop at the first problem; never leaves this module. */
class Stop extends Error {
    readonly problem: TemplateProblem;

    constructor(problem: TemplateProblem) {
        super(`${problem.code} on line ${problem.line}`);
        this.name = 'Stop';
        this.problem = problem;
    }
}

const JSON_STRING = /^"(?:[^"\\\u0000-\u001f]|\\["\\/bfnrt]|\\u[0-9a-fA-F]{4})*"$/;
const JSON_NUMBER = /^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/;
const LITERALS = new Set(['true', 'false', 'null']);
const CLOSERS = new Set(['}', ']']);
const VALUE_STARTS = new Set(['{', '[']);

class Reader {
    private readonly text: string;
    private readonly pieces: readonly Lexeme[];

    constructor(text: string) {
        this.text = text;
        this.pieces = lex(text);
    }

    /** The whole text as one value; throws `Stop` at the first problem. */
    document(): void {
        const first = this.pieces[0];
        if (first === undefined) {
            throw new Stop({ code: 'unfinished', line: 1 });
        }
        const next = this.value(0, null);
        const extra = this.pieces[next];
        if (extra !== undefined) {
            throw new Stop({ code: 'unexpected', line: extra.line });
        }
    }

    private source(piece: Lexeme): string {
        return this.text.slice(piece.from, piece.to);
    }

    private isMark(piece: Lexeme | undefined, marks: ReadonlySet<string> | string): boolean {
        if (piece === undefined || piece.kind !== 'mark') {
            return false;
        }
        const mark = this.source(piece);
        return typeof marks === 'string' ? mark === marks : marks.has(mark);
    }

    /** Whether a piece can start a value. */
    private startsValue(piece: Lexeme): boolean {
        return piece.kind === 'string' || piece.kind === 'number' || piece.kind === 'word' || this.isMark(piece, VALUE_STARTS);
    }

    /**
     * The value at `index`, read; answers the index after it. `after` is the piece the value is asked for after (a
     * colon, a comma, an opening bracket), null at the start of the text.
     */
    private value(index: number, after: Lexeme | null): number {
        const piece = this.pieces[index];
        if (piece === undefined) {
            throw new Stop({ code: 'unfinished', line: after?.line ?? 1 });
        }
        if (this.isMark(piece, '{')) {
            return this.object(index);
        }
        if (this.isMark(piece, '[')) {
            return this.array(index);
        }
        if (piece.kind === 'string') {
            this.string(piece);
            return index + 1;
        }
        if (piece.kind === 'number' && JSON_NUMBER.test(this.source(piece))) {
            return index + 1;
        }
        if (piece.kind === 'word' && LITERALS.has(this.source(piece))) {
            return index + 1;
        }
        if (after !== null && piece.line > after.line && (this.isMark(piece, CLOSERS) || this.isMark(piece, ','))) {
            throw new Stop({ code: 'unfinished', line: after.line });
        }
        throw new Stop({ code: 'unexpected', line: piece.line });
    }

    private string(piece: Lexeme): void {
        if (!piece.closed) {
            throw new Stop({ code: 'unfinished', line: piece.line });
        }
        if (!JSON_STRING.test(this.source(piece))) {
            throw new Stop({ code: 'unexpected', line: piece.line });
        }
    }

    /** What follows a member or an item: a comma (answers the index after it) or the closer (answers null). */
    private separator(index: number, opener: Lexeme, closer: '}' | ']'): number | null {
        const piece = this.pieces[index];
        const previous = this.pieces[index - 1];
        if (piece === undefined) {
            throw new Stop({ code: 'unclosed', line: opener.line, bracket: closer === '}' ? '{' : '[' });
        }
        if (this.isMark(piece, closer)) {
            return null;
        }
        if (this.isMark(piece, ',')) {
            const next = this.pieces[index + 1];
            if (next === undefined) {
                throw new Stop({ code: 'unfinished', line: piece.line });
            }
            if (this.isMark(next, CLOSERS)) {
                throw new Stop({ code: 'trailing-comma', line: piece.line });
            }
            return index + 1;
        }
        if (this.isMark(piece, CLOSERS)) {
            throw new Stop({ code: 'unclosed', line: opener.line, bracket: closer === '}' ? '{' : '[' });
        }
        if (this.startsValue(piece) && previous !== undefined) {
            throw new Stop({ code: 'missing-comma', line: piece.line, after: previous.line });
        }
        throw new Stop({ code: 'unexpected', line: piece.line });
    }

    /**
     * Refuses a value that is the next member's name: a string on a later line than the colon, followed by a colon of
     * its own, means the colon's line was left without its value.
     */
    private notAName(index: number, colon: Lexeme): void {
        const value = this.pieces[index - 1];
        if (value !== undefined && value.kind === 'string' && value.line > colon.line && this.isMark(this.pieces[index], ':')) {
            throw new Stop({ code: 'unfinished', line: colon.line });
        }
    }

    private object(index: number): number {
        const opener = this.pieces[index];
        if (opener === undefined) {
            throw new Stop({ code: 'unfinished', line: 1 });
        }
        let position = index + 1;
        if (this.isMark(this.pieces[position], '}')) {
            return position + 1;
        }
        let after: Lexeme = opener;
        for (;;) {
            const key = this.pieces[position];
            if (key === undefined) {
                throw new Stop({ code: 'unfinished', line: after.line });
            }
            if (key.kind !== 'string') {
                throw new Stop({ code: 'unexpected', line: key.line });
            }
            this.string(key);
            const colon = this.pieces[position + 1];
            if (colon === undefined) {
                throw new Stop({ code: 'unfinished', line: key.line });
            }
            if (!this.isMark(colon, ':')) {
                throw new Stop(colon.line > key.line ? { code: 'unfinished', line: key.line } : { code: 'unexpected', line: colon.line });
            }
            position = this.value(position + 2, colon);
            this.notAName(position, colon);
            const next = this.separator(position, opener, '}');
            if (next === null) {
                return position + 1;
            }
            after = this.pieces[position] ?? opener;
            position = next;
        }
    }

    private array(index: number): number {
        const opener = this.pieces[index];
        if (opener === undefined) {
            throw new Stop({ code: 'unfinished', line: 1 });
        }
        let position = index + 1;
        if (this.isMark(this.pieces[position], ']')) {
            return position + 1;
        }
        let after: Lexeme = opener;
        for (;;) {
            position = this.value(position, after);
            const next = this.separator(position, opener, ']');
            if (next === null) {
                return position + 1;
            }
            after = this.pieces[position] ?? opener;
            position = next;
        }
    }
}

/** How many lines the editor numbers for a text. */
export const lineCount = (text: string): number => text.split('\n').length;

/** Whether the text is JSON; when it is not, the first problem met. */
export function checkTemplate(text: string): TemplateCheck {
    try {
        new Reader(text).document();
    } catch (error: unknown) {
        if (error instanceof Stop) {
            return { kind: 'invalid', problem: error.problem };
        }
        throw error;
    }
    return { kind: 'valid', lines: lineCount(text), fields: fieldCount(text) };
}
