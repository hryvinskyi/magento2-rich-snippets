/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the Overview draws from its state: which regions show, how each tone looks, what the "What Google can show"
 * card holds while the numbers load or after they failed, and the sentences the page words itself. Pure: no words,
 * no framework; the page's components only bind these to markup and phrases.
 *
 * The status card, the page map and the attention list do not depend on the numbers, so they stay while the numbers
 * load or fail; only the Google card changes.
 */
import { phrase, type Phrase } from '@hryvinskyi/admin-ui';
import type {
    AttentionTone,
    EmptyNote,
    FeatureRow,
    FeatureStatus,
    Features,
    SetupState,
    StatusTone,
} from './model.ts';

/** The regions a setup state shows: the first-visit hero alone, or the store bar, the status and the columns. */
export interface Regions {
    readonly hero: boolean;
    readonly setUp: boolean;
}

const REGIONS = {
    none: { hero: true, setUp: false },
    off: { hero: false, setUp: true },
    live: { hero: false, setUp: true },
} as const satisfies Record<SetupState, Regions>;

export const regionsOf = (state: SetupState): Regions => REGIONS[state];

/** How the status card looks: the kit's status state (empty for a neutral one), its glyph, and its action's weight. */
export interface StatusLook {
    readonly state: 'ok' | 'invalid' | '';
    readonly icon: string;
    /** The one forward action when nothing is on; an ordinary one otherwise. */
    readonly action: 'primary' | 'secondary';
}

const STATUS_LOOKS = {
    ok: { state: 'ok', icon: 'check_circle', action: 'secondary' },
    invalid: { state: 'invalid', icon: 'error', action: 'secondary' },
    off: { state: '', icon: 'visibility_off', action: 'primary' },
    none: { state: '', icon: 'visibility_off', action: 'primary' },
} as const satisfies Record<StatusTone, StatusLook>;

export const statusLookOf = (tone: StatusTone): StatusLook => STATUS_LOOKS[tone];

/** How a feature's verdict looks: the kit's state-pill tone and its glyph. */
export interface PillLook {
    readonly tone: 'ok' | 'error' | 'muted';
    readonly icon: string;
}

const PILL_LOOKS = {
    met: { tone: 'ok', icon: 'check_circle' },
    partly: { tone: 'error', icon: 'error' },
    no_result: { tone: 'muted', icon: 'remove_circle' },
} as const satisfies Record<FeatureStatus, PillLook>;

export const pillLookOf = (status: FeatureStatus): PillLook => PILL_LOOKS[status];

/** How an item of the attention list looks: its filled glyph and the tone it is drawn in. */
export interface AttentionLook {
    readonly icon: string;
    readonly tone: 'error' | 'warning' | 'muted';
}

const ATTENTION_LOOKS = {
    bad: { icon: 'error', tone: 'error' },
    warn: { icon: 'warning', tone: 'warning' },
    info: { icon: 'info', tone: 'muted' },
} as const satisfies Record<AttentionTone, AttentionLook>;

export const attentionLookOf = (tone: AttentionTone): AttentionLook => ATTENTION_LOOKS[tone];

/** Where the numbers of the store view picked stand: shown, on their way, or refused. */
export type NumbersState = 'ready' | 'loading' | 'failed';

interface FeatureBodies {
    loading: Record<never, never>;
    failed: Record<never, never>;
    empty: { readonly note: EmptyNote };
    rows: { readonly rows: readonly FeatureRow[]; readonly footer: string | null };
}

export type FeatureBodyKind = keyof FeatureBodies;

/** What the "What Google can show" card holds under its head. */
export type FeatureBody<K extends FeatureBodyKind = FeatureBodyKind> =
    { [P in K]: { readonly kind: P } & FeatureBodies[P] }[K];

/** The card's body: the numbers' own state first, then the rows, or the server's note in their place. */
export function featureBodyOf(numbers: NumbersState, features: Features): FeatureBody {
    if (numbers !== 'ready') {
        return { kind: numbers };
    }
    if (features.rows.length === 0 && features.empty !== null) {
        return { kind: 'empty', note: features.empty };
    }
    return { kind: 'rows', rows: features.rows, footer: features.footer };
}

/** The line under the card's title: the server's, or the page's own while the numbers load or after they failed. */
export function featureSubtitleOf(numbers: NumbersState, features: Features): Phrase | string {
    const own = { ready: null, loading: phrase('page.loading'), failed: phrase('page.unavailable') } as const;
    return own[numbers] ?? features.subtitle;
}

/** The line after the store-view picker: every store view together, or what the one picked outputs. */
export function storeLineOf(view: { readonly name: string } | null): Phrase {
    return view === null ? phrase('overview.store.allLine') : phrase('overview.store.oneLine', view.name);
}

/** The storefront the header opens: the home page of the store view picked, or the default one. */
export function storefrontOf(
    stores: readonly { readonly id: number; readonly home: string }[],
    store: number | null,
    defaultHome: string,
): string {
    const home = stores.find((entry) => entry.id === store)?.home ?? '';
    return home === '' ? defaultHome : home;
}
