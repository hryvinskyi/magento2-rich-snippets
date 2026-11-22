/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the "Whole catalog" mode decides before it words anything: whether a check is on its way, how the status and
 * each finding look, what the Findings card shows, what the page says when a check it watched has ended, and where
 * the pages behind a finding open in the admin.
 *
 * - A check is on its way while it is asked for or running; the page then asks where it stands every few seconds.
 * - The Findings card shows that a check is on its way, else the findings, else that the last check found nothing,
 *   else that no check has run yet.
 * - A check the page watched end says what the server says of the finished run, or that it stopped, when the newest
 *   run failed.
 * - A product and a category open in their editor in the store view they were checked in; a CMS page in its own; a
 *   page of any other kind has no screen of its own.
 */
import { linkTo, withParam, type ScreenLink } from '@hryvinskyi/admin-ui';
import type { CheckStatus, FindingItem, FindingsTable, FindingTone, StatusTone } from './model.ts';

/** How often the page asks where a check on its way stands. */
export const POLL_MS = 3_000;

export const PRODUCTS = 'catalog/product/index';

/** Whether a check is on its way. */
export const isChecking = (status: CheckStatus): boolean => status.state !== 'idle';

export interface StatusLook {
    /** The kit status's `data-state`; empty for the neutral look. */
    readonly state: '' | 'ok' | 'invalid';
    readonly icon: string;
}

export const STATUS_LOOKS = Object.freeze({
    invalid: { state: 'invalid', icon: 'error' },
    ok: { state: 'ok', icon: 'check_circle' },
    busy: { state: '', icon: 'progress_activity' },
    none: { state: '', icon: 'schedule' },
} as const satisfies Record<StatusTone, StatusLook>);

export const FINDING_ICONS = Object.freeze({
    bad: 'error',
    warn: 'warning',
    note: 'info',
} as const satisfies Record<FindingTone, string>);

/** What the Findings card shows. */
export type FindingsBody = 'checking' | 'rows' | 'clean' | 'none';

export function findingsBody(status: CheckStatus, table: FindingsTable): FindingsBody {
    if (isChecking(status)) {
        return 'checking';
    }
    if (table.findings.length > 0) {
        return 'rows';
    }
    return status.last === null ? 'none' : 'clean';
}

/** What the page says once a check it watched has ended. */
export interface RunEnd {
    readonly tone: 'success' | 'error';
    readonly text: string;
}

/** The message for a check that was on its way and is no longer; null when none ended between the two. */
export function runEnded(before: CheckStatus, after: CheckStatus): RunEnd | null {
    if (!isChecking(before) || isChecking(after)) {
        return null;
    }
    if (after.failed !== null) {
        return { tone: 'error', text: after.failed };
    }
    return after.last === null ? null : { tone: 'success', text: after.last.toast };
}

interface ItemScreen {
    readonly route: string;
    readonly param: string;
    readonly scoped: boolean;
}

/** The admin screen of a page of each kind, the parameter that names it, and whether it opens in a store view. */
const ITEM_SCREENS: Readonly<Record<string, ItemScreen>> = Object.freeze({
    product: { route: 'catalog/product/edit', param: 'id', scoped: true },
    category: { route: 'catalog/category/edit', param: 'id', scoped: true },
    cms: { route: 'cms/page/edit', param: 'page_id', scoped: false },
});

/** The admin screen of a page behind a finding; null when its kind has none. */
export function itemLink(item: FindingItem): ScreenLink | null {
    const screen = ITEM_SCREENS[item.kind];
    if (screen === undefined || item.id === null) {
        return null;
    }
    const named = withParam(linkTo(screen.route), screen.param, String(item.id));
    return screen.scoped ? withParam(named, 'store', String(item.store)) : named;
}
