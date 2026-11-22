/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What a template string holds, read the way the template engine reads it: literal text and `{{ … }}` expressions,
 * `{{{{` standing for a literal `{{`, the `}}` that closes an expression looked for outside quotes; and whether an
 * expression is one store field with its filters, which the builder draws as a field.
 *
 * A field is a path (`product.manufacturer`; a keyword such as `not` is not a path's first segment) followed by
 * filters, each a name and its arguments (`limit:5000`, `default:'Linden & Co.'`); arguments are quoted strings,
 * numbers, `true`, `false` or `null`. A last `default` filter with one string argument is the field's fallback ("Use
 * this value instead"). Anything else — a comparison, a literal, `or` — is not a field.
 *
 * Printing writes a field as `{{path | filter | filter:arg,arg}}`, a string argument single-quoted with its backslash
 * and quote escaped, and escapes `{{` in literal text as `{{{{`.
 */

export type TextPart =
    | { readonly kind: 'text'; readonly text: string }
    | { readonly kind: 'expression'; readonly source: string };

/** A filter applied to a field, as the builder prints it back: `name` or `name:arg,arg`. */
export interface FieldFilter {
    readonly name: string;
    /** The arguments as written in the expression's language. */
    readonly args: readonly string[];
}

export interface FieldExpression {
    readonly path: string;
    readonly filters: readonly FieldFilter[];
    /** The `default` fallback; null when the field has none. */
    readonly fallback: string | null;
}

const OPEN = '{{';
const ESCAPED_OPEN = '{{{{';
const CLOSE = '}}';

function closeOf(value: string, start: number): number | null {
    let quote: string | null = null;
    for (let position = start; position < value.length; position++) {
        const char = value.charAt(position);
        if (quote !== null) {
            position += char === '\\' ? 1 : 0;
            quote = char === quote ? null : quote;
            continue;
        }
        if (char === '\'' || char === '"') {
            quote = char;
            continue;
        }
        if (value.startsWith(CLOSE, position)) {
            return position;
        }
    }
    return null;
}

/**
 * The parts of a template string; null when the string holds a `{{` without its `}}` or an empty expression, which
 * the engine refuses.
 */
export function splitText(value: string): readonly TextPart[] | null {
    const parts: TextPart[] = [];
    let text = '';
    let position = 0;
    for (let open = value.indexOf(OPEN, position); open !== -1; open = value.indexOf(OPEN, position)) {
        text += value.slice(position, open);
        if (value.startsWith(ESCAPED_OPEN, open)) {
            text += OPEN;
            position = open + 4;
            continue;
        }
        const close = closeOf(value, open + 2);
        if (close === null) {
            return null;
        }
        const source = value.slice(open + 2, close);
        if (source.trim() === '') {
            return null;
        }
        if (text !== '') {
            parts.push({ kind: 'text', text });
            text = '';
        }
        parts.push({ kind: 'expression', source });
        position = close + 2;
    }
    text += value.slice(position);
    if (text !== '') {
        parts.push({ kind: 'text', text });
    }
    return parts;
}

/** Literal text as a template string holds it: every `{{` doubled, left to right. */
export const escapedText = (text: string): string => text.replaceAll(OPEN, ESCAPED_OPEN);

/** Whether a string holds an expression; a string without one is literal text. */
export const hasExpression = (parts: readonly TextPart[]): boolean => parts.some((part) => part.kind === 'expression');

/** The literal text of parts that hold no expression. */
export const literalOf = (parts: readonly TextPart[]): string =>
    parts.map((part) => (part.kind === 'text' ? part.text : '')).join('');

type Token =
    | { readonly kind: 'name'; readonly text: string }
    | { readonly kind: 'number'; readonly text: string }
    | { readonly kind: 'string'; readonly value: string }
    | { readonly kind: 'symbol'; readonly text: string };

const KEYWORDS = new Set(['and', 'or', 'not', 'true', 'false', 'null']);
const NAME = /^[A-Za-z_][A-Za-z0-9_]*/;
const NUMBER = /^-?\d+(?:\.\d+)?/;
const SYMBOLS = new Set(['.', '|', ':', ',']);

function readQuoted(source: string, start: number): { readonly value: string; readonly end: number } | null {
    const quote = source.charAt(start);
    let value = '';
    for (let position = start + 1; position < source.length; position++) {
        let char = source.charAt(position);
        if (char === quote) {
            return { value, end: position + 1 };
        }
        if (char === '\\' && position + 1 < source.length) {
            position++;
            char = source.charAt(position);
        }
        value += char;
    }
    return null;
}

function tokens(source: string): readonly Token[] | null {
    const found: Token[] = [];
    let position = 0;
    while (position < source.length) {
        const rest = source.slice(position);
        const char = rest.charAt(0);
        if (/\s/.test(char)) {
            position++;
            continue;
        }
        if (char === '\'' || char === '"') {
            const read = readQuoted(source, position);
            if (read === null) {
                return null;
            }
            found.push({ kind: 'string', value: read.value });
            position = read.end;
            continue;
        }
        const name = NAME.exec(rest);
        const number = NUMBER.exec(rest);
        const next: Exclude<Token, { readonly kind: 'string' }> | null = name !== null
            ? { kind: 'name', text: name[0] }
            : number !== null
                ? { kind: 'number', text: number[0] }
                : SYMBOLS.has(char) ? { kind: 'symbol', text: char } : null;
        if (next === null) {
            return null;
        }
        found.push(next);
        position += next.text.length;
    }
    return found;
}

/** A string argument as the expression language writes it: single quotes, backslash and quote escaped. */
export const quotedArgument = (value: string): string => `'${value.replaceAll('\\', '\\\\').replaceAll('\'', '\\\'')}'`;

class FieldReader {
    private index = 0;

    private readonly found: readonly Token[];

    constructor(found: readonly Token[]) {
        this.found = found;
    }

    private peek(): Token | undefined {
        return this.found[this.index];
    }

    private isSymbol(text: string): boolean {
        const token = this.peek();
        return token !== undefined && token.kind === 'symbol' && token.text === text;
    }

    private take(): Token | undefined {
        const token = this.peek();
        this.index++;
        return token;
    }

    private path(): string | null {
        const first = this.take();
        if (first === undefined || first.kind !== 'name' || KEYWORDS.has(first.text)) {
            return null;
        }
        const segments = [first.text];
        while (this.isSymbol('.')) {
            this.index++;
            const segment = this.take();
            if (segment === undefined || (segment.kind !== 'name' && !(segment.kind === 'number' && /^\d+$/.test(segment.text)))) {
                return null;
            }
            segments.push(segment.text);
        }
        return segments.join('.');
    }

    /** An argument as written back; strings keep their value so a fallback can be read. */
    private argument(): { readonly text: string; readonly string: string | null } | null {
        const token = this.take();
        if (token === undefined || token.kind === 'symbol') {
            return null;
        }
        if (token.kind === 'string') {
            return { text: quotedArgument(token.value), string: token.value };
        }
        if (token.kind === 'name' && !['true', 'false', 'null'].includes(token.text)) {
            return null;
        }
        return { text: token.text, string: null };
    }

    field(): FieldExpression | null {
        const path = this.path();
        if (path === null) {
            return null;
        }
        const filters: FieldFilter[] = [];
        let fallback: string | null = null;
        while (this.isSymbol('|')) {
            this.index++;
            const name = this.take();
            if (name === undefined || name.kind !== 'name') {
                return null;
            }
            const args: { readonly text: string; readonly string: string | null }[] = [];
            if (this.isSymbol(':')) {
                do {
                    this.index++;
                    const argument = this.argument();
                    if (argument === null) {
                        return null;
                    }
                    args.push(argument);
                } while (this.isSymbol(','));
            }
            const only = args.length === 1 ? args[0] : undefined;
            if (fallback !== null) {
                filters.push({ name: 'default', args: [quotedArgument(fallback)] });
                fallback = null;
            }
            if (name.text === 'default' && only !== undefined && only.string !== null) {
                fallback = only.string;
                continue;
            }
            filters.push({ name: name.text, args: args.map((argument) => argument.text) });
        }
        return this.index === this.found.length ? { path, filters, fallback } : null;
    }
}

/** The field an expression's source reads; null when it is not one field with filters. */
export function fieldOf(source: string): FieldExpression | null {
    const found = tokens(source);
    return found === null ? null : new FieldReader(found).field();
}

/** A field as the builder prints it inside a template string. */
export function printField(field: FieldExpression): string {
    const filters = field.filters.map((filter) => (filter.args.length === 0 ? filter.name : `${filter.name}:${filter.args.join(',')}`));
    const all = field.fallback === null ? filters : [...filters, `default:${quotedArgument(field.fallback)}`];
    return `{{${[field.path, ...all].join(' | ')}}}`;
}
