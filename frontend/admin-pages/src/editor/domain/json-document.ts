/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A JSON document as written, for the template projection: object members keep the order they were written in
 * (a key that reads as a number included, which a plain object would move to the front), and a number keeps the
 * digits it was written with, so a value the builder leaves alone is printed back as it came.
 *
 * A key written twice keeps its first place and its last value, as the server's decoder reads it.
 *
 * Printing writes two spaces per level, one member or item per line, and an empty object or list as `{}` or `[]`.
 */

interface JsonParts {
    text: { readonly value: string };
    /** `raw` is the number as written. */
    number: { readonly raw: string };
    flag: { readonly value: boolean };
    null: Record<never, never>;
    list: { readonly items: readonly Json[] };
    record: { readonly entries: readonly JsonEntry[] };
}

export type JsonType = keyof JsonParts;

/** One JSON value; `type` says which. */
export type Json<K extends JsonType = JsonType> = { [P in K]: { readonly type: P } & JsonParts[P] }[K];

export type JsonEntry = readonly [key: string, value: Json];

export type JsonRecord = Json<'record'>;
export type JsonList = Json<'list'>;

/** The text is not JSON. */
export class JsonSyntaxError extends Error {
    readonly offset: number;

    constructor(offset: number) {
        super(`The text is not JSON at offset ${String(offset)}.`);
        this.name = 'JsonSyntaxError';
        this.offset = offset;
    }
}

export const jsonText = (value: string): Json<'text'> => ({ type: 'text', value });
export const jsonNumber = (raw: string): Json<'number'> => ({ type: 'number', raw });
export const jsonFlag = (value: boolean): Json<'flag'> => ({ type: 'flag', value });
export const JSON_NULL: Json<'null'> = Object.freeze({ type: 'null' });
export const jsonList = (items: readonly Json[]): JsonList => ({ type: 'list', items });
export const jsonRecord = (entries: readonly JsonEntry[]): JsonRecord => ({ type: 'record', entries });

/** The value of a member; undefined when the record has none of that name. */
export const memberOf = (record: JsonRecord, key: string): Json | undefined =>
    record.entries.find((entry) => entry[0] === key)?.[1];

const NUMBER = /^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/;
const WHITESPACE = new Set([' ', '\t', '\n', '\r']);
const ESCAPES: Readonly<Record<string, string>> = Object.freeze({
    '"': '"', '\\': '\\', '/': '/', b: '\b', f: '\f', n: '\n', r: '\r', t: '\t',
});

class Reader {
    private position = 0;

    private readonly source: string;

    constructor(source: string) {
        this.source = source;
    }

    document(): Json {
        const value = this.value();
        this.skip();
        if (this.position !== this.source.length) {
            throw new JsonSyntaxError(this.position);
        }
        return value;
    }

    private skip(): void {
        while (this.position < this.source.length && WHITESPACE.has(this.source.charAt(this.position))) {
            this.position++;
        }
    }

    private value(): Json {
        this.skip();
        const readers: Readonly<Record<string, () => Json>> = {
            '{': () => this.record(),
            '[': () => this.list(),
            '"': () => jsonText(this.text()),
            t: () => this.word('true', jsonFlag(true)),
            f: () => this.word('false', jsonFlag(false)),
            n: () => this.word('null', JSON_NULL),
        };
        const read = readers[this.source.charAt(this.position)];
        return read === undefined ? this.number() : read();
    }

    private word(word: string, value: Json): Json {
        if (!this.source.startsWith(word, this.position)) {
            throw new JsonSyntaxError(this.position);
        }
        this.position += word.length;
        return value;
    }

    private number(): Json {
        const match = NUMBER.exec(this.source.slice(this.position));
        if (match === null) {
            throw new JsonSyntaxError(this.position);
        }
        this.position += match[0].length;
        return jsonNumber(match[0]);
    }

    private text(): string {
        this.position++;
        let value = '';
        while (this.position < this.source.length) {
            const char = this.source.charAt(this.position);
            if (char === '"') {
                this.position++;
                return value;
            }
            if (char < ' ') {
                throw new JsonSyntaxError(this.position);
            }
            if (char === '\\') {
                value += this.escape();
                continue;
            }
            value += char;
            this.position++;
        }
        throw new JsonSyntaxError(this.position);
    }

    private escape(): string {
        const code = this.source.charAt(this.position + 1);
        if (code === 'u') {
            const hex = this.source.slice(this.position + 2, this.position + 6);
            if (!/^[0-9a-fA-F]{4}$/.test(hex)) {
                throw new JsonSyntaxError(this.position);
            }
            this.position += 6;
            return String.fromCharCode(Number.parseInt(hex, 16));
        }
        const escaped = ESCAPES[code];
        if (escaped === undefined) {
            throw new JsonSyntaxError(this.position);
        }
        this.position += 2;
        return escaped;
    }

    /** Past the separator after an item or member; true at the closing bracket. */
    private closes(close: string): boolean {
        this.skip();
        const char = this.source.charAt(this.position);
        this.position++;
        if (char === close) {
            return true;
        }
        if (char !== ',') {
            throw new JsonSyntaxError(this.position - 1);
        }
        return false;
    }

    private opensEmpty(close: string): boolean {
        this.position++;
        this.skip();
        if (this.source.charAt(this.position) !== close) {
            return false;
        }
        this.position++;
        return true;
    }

    private list(): Json {
        const items: Json[] = [];
        if (this.opensEmpty(']')) {
            return jsonList(items);
        }
        do {
            items.push(this.value());
        } while (!this.closes(']'));
        return jsonList(items);
    }

    private record(): Json {
        const entries: JsonEntry[] = [];
        if (this.opensEmpty('}')) {
            return jsonRecord(entries);
        }
        do {
            this.skip();
            if (this.source.charAt(this.position) !== '"') {
                throw new JsonSyntaxError(this.position);
            }
            const key = this.text();
            this.skip();
            if (this.source.charAt(this.position) !== ':') {
                throw new JsonSyntaxError(this.position);
            }
            this.position++;
            const value = this.value();
            const known = entries.findIndex((entry) => entry[0] === key);
            if (known === -1) {
                entries.push([key, value]);
            } else {
                entries[known] = [key, value];
            }
        } while (!this.closes('}'));
        return jsonRecord(entries);
    }
}

/** Reads a JSON text; throws `JsonSyntaxError` when it is not JSON. */
export const readJson = (source: string): Json => new Reader(source).document();

/** A string as JSON writes it. */
export const quoted = (value: string): string => JSON.stringify(value);

/** The indentation of a line at `depth`. */
export const indent = (depth: number): string => '  '.repeat(depth);

const PRINTERS: { readonly [K in JsonType]: (value: Json<K>, depth: number) => string } = {
    text: (value) => quoted(value.value),
    number: (value) => value.raw,
    flag: (value) => (value.value ? 'true' : 'false'),
    null: () => 'null',
    list: (value, depth) => (value.items.length === 0
        ? '[]'
        : `[\n${value.items.map((item) => indent(depth + 1) + printJson(item, depth + 1)).join(',\n')}\n${indent(depth)}]`),
    record: (value, depth) => (value.entries.length === 0
        ? '{}'
        : `{\n${value.entries.map(([key, item]) => `${indent(depth + 1)}${quoted(key)}: ${printJson(item, depth + 1)}`).join(',\n')}\n${indent(depth)}}`),
};

/** A JSON value as text, its first line not indented and the lines after it indented for `depth`. */
export function printJson<K extends JsonType>(value: Json<K>, depth = 0): string {
    return PRINTERS[value.type](value, depth);
}
