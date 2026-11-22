/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A template text cut into its pieces, each with its place in the text, and the colours the Code tab draws them in.
 *
 * The pieces (`lex`) are what JSON is made of — a string, a number, a word (`true`, `false`, `null`, or anything else
 * spelled with letters), one of `{ } [ ] , :` — plus a stray character that is none of them. A string runs to its
 * closing quote; one that meets the end of its line or of the text first is unclosed, and says so. Whitespace is no
 * piece. Every piece knows the line it starts on, counted from 1.
 *
 * The colours (`templateTokens`) follow the pieces: a string followed by a colon is a property name — a directive of
 * the template language (`@if`, `@each`, `@as`, `@limit`, `@value`, `@include`) in its own colour — any other string is
 * text, inside which each `{{ … }}` is a field (to its `}}`, or to the end of the string when it has none; `{{{{` is a
 * literal `{{`); a number is a number; the six marks are punctuation; a word or a stray character stays plain. The
 * text may be anything: a template being typed is coloured as far as its pieces go.
 */
import { DIRECTIVES } from './template-tree.ts';

export type LexemeKind = 'string' | 'number' | 'word' | 'mark' | 'stray';

export interface Lexeme {
    readonly kind: LexemeKind;
    /** Where the piece starts in the text (inclusive) and ends (exclusive). */
    readonly from: number;
    readonly to: number;
    /** The line the piece starts on, counted from 1. */
    readonly line: number;
    /** For a string: whether its closing quote was found. Every other piece is closed. */
    readonly closed: boolean;
}

/** The colours of the Code tab, as the kit's code editor names them. */
export type TemplateTokenKind = 'key' | 'string' | 'field' | 'directive' | 'number' | 'punctuation';

export interface TemplateToken {
    readonly kind: TemplateTokenKind;
    readonly from: number;
    readonly to: number;
}

const MARKS = new Set(['{', '}', '[', ']', ',', ':']);
const NUMBER = /-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/y;
const WORD = /[A-Za-z_$][\w$]*/y;
const SPACE = /\s/;

interface Scanner {
    /** Whether a piece of this kind starts at `position`. */
    readonly starts: (text: string, position: number) => boolean;
    /** The end of the piece that starts at `from`, and whether it is closed. */
    readonly scan: (text: string, from: number) => { readonly to: number; readonly closed: boolean };
}

function scanString(text: string, from: number): { readonly to: number; readonly closed: boolean } {
    for (let position = from + 1; position < text.length; position++) {
        const char = text.charAt(position);
        if (char === '\\') {
            position++;
            continue;
        }
        if (char === '"') {
            return { to: position + 1, closed: true };
        }
        if (char === '\n') {
            return { to: position, closed: false };
        }
    }
    return { to: text.length, closed: false };
}

const sticky = (pattern: RegExp) => (text: string, from: number): { readonly to: number; readonly closed: boolean } => {
    pattern.lastIndex = from;
    const match = pattern.exec(text);
    return { to: match === null ? from + 1 : from + match[0].length, closed: true };
};

/** The scanners, tried in order; the last one takes any character. */
const SCANNERS: readonly (Scanner & { readonly kind: LexemeKind })[] = [
    { kind: 'string', starts: (text, position) => text.charAt(position) === '"', scan: scanString },
    { kind: 'number', starts: (text, position) => /^-?\d/.test(text.slice(position, position + 2)), scan: sticky(NUMBER) },
    { kind: 'word', starts: (text, position) => /[A-Za-z_$]/.test(text.charAt(position)), scan: sticky(WORD) },
    { kind: 'mark', starts: (text, position) => MARKS.has(text.charAt(position)), scan: (_text, from) => ({ to: from + 1, closed: true }) },
    { kind: 'stray', starts: () => true, scan: (_text, from) => ({ to: from + 1, closed: true }) },
];

/** The pieces of a text, in order. */
export function lex(text: string): readonly Lexeme[] {
    const lexemes: Lexeme[] = [];
    let line = 1;
    let position = 0;
    while (position < text.length) {
        const char = text.charAt(position);
        if (SPACE.test(char)) {
            line += char === '\n' ? 1 : 0;
            position++;
            continue;
        }
        const scanner = SCANNERS.find((candidate) => candidate.starts(text, position));
        if (scanner === undefined) {
            break;
        }
        const { to, closed } = scanner.scan(text, position);
        lexemes.push({ kind: scanner.kind, from: position, to, line, closed });
        position = to;
    }
    return lexemes;
}

const OPEN = '{{';
const ESCAPED_OPEN = '{{{{';
const CLOSE = '}}';

/** Where the expression opened at `start` closes: its `}}` outside quotes, or the end of the string. */
function expressionEnd(text: string, start: number, end: number): number {
    let quote: string | null = null;
    for (let position = start + OPEN.length; position < end; position++) {
        const char = text.charAt(position);
        if (quote !== null) {
            position += char === '\\' ? 1 : 0;
            quote = char === quote ? null : quote;
            continue;
        }
        if (char === '\'') {
            quote = char;
            continue;
        }
        if (text.startsWith(CLOSE, position)) {
            return position + CLOSE.length;
        }
    }
    return end;
}

/** The text and field runs of a string's inside, between its quotes (or to its end when it is unclosed). */
function stringRuns(text: string, lexeme: Lexeme): readonly TemplateToken[] {
    const end = lexeme.closed ? lexeme.to - 1 : lexeme.to;
    const runs: TemplateToken[] = [];
    let textFrom = lexeme.from;
    let position = lexeme.from + 1;
    while (position < end) {
        if (text.startsWith(ESCAPED_OPEN, position)) {
            position += ESCAPED_OPEN.length;
            continue;
        }
        if (!text.startsWith(OPEN, position)) {
            position++;
            continue;
        }
        const fieldEnd = expressionEnd(text, position, end);
        if (position > textFrom) {
            runs.push({ kind: 'string', from: textFrom, to: position });
        }
        runs.push({ kind: 'field', from: position, to: fieldEnd });
        textFrom = fieldEnd;
        position = fieldEnd;
    }
    if (lexeme.to > textFrom) {
        runs.push({ kind: 'string', from: textFrom, to: lexeme.to });
    }
    return runs;
}

const isColon = (text: string, lexeme: Lexeme | undefined): boolean =>
    lexeme !== undefined && lexeme.kind === 'mark' && text.charAt(lexeme.from) === ':';

/** The name a string piece holds, without its quotes; the raw inside for one that is unclosed. */
const stringInside = (text: string, lexeme: Lexeme): string =>
    text.slice(lexeme.from + 1, lexeme.closed ? lexeme.to - 1 : lexeme.to);

type Colourer = (text: string, lexeme: Lexeme, next: Lexeme | undefined) => readonly TemplateToken[];

const one = (kind: TemplateTokenKind): Colourer => (_text, lexeme) => [{ kind, from: lexeme.from, to: lexeme.to }];

const COLOURERS: { readonly [K in LexemeKind]: Colourer } = {
    string: (text, lexeme, next) => {
        if (!isColon(text, next)) {
            return stringRuns(text, lexeme);
        }
        const kind: TemplateTokenKind = DIRECTIVES.has(stringInside(text, lexeme)) ? 'directive' : 'key';
        return [{ kind, from: lexeme.from, to: lexeme.to }];
    },
    number: one('number'),
    word: () => [],
    mark: one('punctuation'),
    stray: () => [],
};

/** The coloured runs of a template text, in order; what no run covers is plain. */
export function templateTokens(text: string): readonly TemplateToken[] {
    const lexemes = lex(text);
    return lexemes.flatMap((lexeme, index) => COLOURERS[lexeme.kind](text, lexeme, lexemes[index + 1]));
}

/** How many fields (`{{ … }}`) the text's strings hold. */
export const fieldCount = (text: string): number =>
    templateTokens(text).filter((token) => token.kind === 'field').length;
