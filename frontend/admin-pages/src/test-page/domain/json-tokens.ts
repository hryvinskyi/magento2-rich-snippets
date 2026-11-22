/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The colours of a JSON text, as the code blocks of the page test draw it: a property name is a key, a value in
 * quotes a string, a number a number, the braces, brackets, commas and colons punctuation; `true`, `false`, `null`
 * and anything that is not JSON stay plain. A text that is not valid JSON is still coloured as far as its pieces go,
 * so a block whose JSON cannot be read shows as written.
 */

/** A run of the text of one colour, as a range of the whole text (`from` inclusive, `to` exclusive). */
export interface JsonToken {
    readonly kind: 'key' | 'string' | 'number' | 'punctuation';
    readonly from: number;
    readonly to: number;
}

/** A string (with its escapes), followed by a colon when it names a property; a number; one punctuation mark. */
const PIECE = /("(?:[^"\\\n]|\\.)*")(\s*:)?|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)|([{}[\],:])/g;

export function jsonTokens(text: string): readonly JsonToken[] {
    const tokens: JsonToken[] = [];
    for (const match of text.matchAll(PIECE)) {
        const from = match.index;
        const [whole, quoted, colon, number] = match;
        if (quoted !== undefined) {
            tokens.push({ kind: colon === undefined ? 'string' : 'key', from, to: from + quoted.length });
            if (colon !== undefined) {
                tokens.push({ kind: 'punctuation', from: from + whole.length - 1, to: from + whole.length });
            }
        } else {
            tokens.push({ kind: number === undefined ? 'punctuation' : 'number', from, to: from + whole.length });
        }
    }
    return tokens;
}
