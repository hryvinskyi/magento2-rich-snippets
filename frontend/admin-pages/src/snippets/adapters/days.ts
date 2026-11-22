/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The server's dates in the admin's locale, through the browser's date formats. A date the server writes as
 * `Y-m-d H:i:s` is UTC; one written in ISO 8601 says its own offset. Shown in the browser's time zone; a text that is
 * no date is shown as it is.
 */
import type { Days } from '../domain/wording.ts';

const SERVER_DATE = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/;

/** The moment a server date names; null for a text that is no date. */
function momentOf(at: string): Date | null {
    const plain = SERVER_DATE.exec(at);
    const moment = plain === null ? new Date(at) : new Date(`${at.replace(' ', 'T')}Z`);
    return Number.isNaN(moment.getTime()) ? null : moment;
}

/** `en_US` → `en-US`; a locale the browser does not know falls back to its own. */
function tagOf(locale: string): string | undefined {
    const tag = locale.replace('_', '-');
    try {
        return Intl.DateTimeFormat.supportedLocalesOf([tag]).length > 0 ? tag : undefined;
    } catch {
        return undefined;
    }
}

export function createDays(locale: string, timeZone?: string): Days {
    const tag = tagOf(locale);
    const format = (options: Intl.DateTimeFormatOptions): ((at: string) => string) => {
        const formatter = new Intl.DateTimeFormat(tag, timeZone === undefined ? options : { ...options, timeZone });
        return (at: string): string => {
            const moment = momentOf(at);
            return moment === null ? at : formatter.format(moment);
        };
    };
    return Object.freeze({
        short: format({ day: 'numeric', month: 'short' }),
        long: format({ day: 'numeric', month: 'long' }),
        full: format({ day: 'numeric', month: 'long', year: 'numeric' }),
    });
}
