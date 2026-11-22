/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The fields the Code tab offers while a field is typed. Two braces open the offer: from the `{{` before the caret, on
 * the caret's line and with nothing typed after it but a path (letters, digits, `_` and `.`, after optional spaces), the
 * catalog's fields whose path or label holds what was typed are offered — the ones whose path starts with it first,
 * then the rest, each group in the catalog's order, at most `MAX_OFFERED`. Nothing is offered once the expression is
 * closed, or when nothing in the catalog matches.
 *
 * A choice replaces the path typed so far, including the part of it after the caret, and closes the expression with
 * `}}` unless it is closed already.
 */
import type { FieldCatalog } from './model.ts';

/** One field offered: the text it inserts, the path the list shows, and the field's label beside it. */
export interface OfferedField {
    readonly value: string;
    readonly label: string;
    readonly detail: string;
}

/** What is offered at the caret: the range a choice replaces and the fields to choose from. */
export interface FieldOffer {
    readonly from: number;
    readonly to: number;
    readonly items: readonly OfferedField[];
}

export const MAX_OFFERED = 40;

const OPEN = '{{';
const CLOSE = '}}';
const TYPED = /^\s*([A-Za-z0-9_.]*)$/;
const PATH_REST = /^[A-Za-z0-9_.]*/;

/** The path being typed at the caret and where it starts; null when the caret is not in an open expression. */
function typedPath(text: string, caret: number): { readonly query: string; readonly from: number } | null {
    const lineStart = text.lastIndexOf('\n', caret - 1) + 1;
    const open = text.lastIndexOf(OPEN, caret - OPEN.length);
    if (open < lineStart) {
        return null;
    }
    const typed = TYPED.exec(text.slice(open + OPEN.length, caret));
    if (typed === null) {
        return null;
    }
    const query = typed[1] ?? '';
    return { query, from: caret - query.length };
}

const holds = (value: string, query: string): boolean => value.toLowerCase().includes(query);

/** The fields to offer at the caret of a text; null for none. */
export function offerFields(catalog: FieldCatalog | null, text: string, caret: number): FieldOffer | null {
    if (catalog === null) {
        return null;
    }
    const typed = typedPath(text, caret);
    if (typed === null) {
        return null;
    }
    const rest = PATH_REST.exec(text.slice(caret))?.[0] ?? '';
    const to = caret + rest.length;
    const closed = /^\s*\}\}/.test(text.slice(to));
    const query = typed.query.toLowerCase();
    const fields = catalog.groups.flatMap((group) => group.fields);
    const leading = fields.filter((field) => field.path.toLowerCase().startsWith(query));
    const others = fields.filter((field) => !field.path.toLowerCase().startsWith(query) && (holds(field.path, query) || holds(field.label, query)));
    const items = [...leading, ...others].slice(0, MAX_OFFERED).map((field) => ({
        value: closed ? field.path : `${field.path}${CLOSE}`,
        label: field.path,
        detail: field.label,
    }));
    return items.length === 0 ? null : { from: typed.from, to, items };
}
