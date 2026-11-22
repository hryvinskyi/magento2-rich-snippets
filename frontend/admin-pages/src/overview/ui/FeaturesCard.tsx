/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * "What Google can show": one row per kind of result with the pages it is for, a bar of how much of the shop has what
 * it needs, the server's line under it and its verdict; then where the numbers come from. While the numbers of the
 * store view picked load it shows grey rows, after they failed it says so with *Try again* (unless trying again
 * cannot help), and with nothing to count it shows the server's note in place of the rows.
 */
import type { JSX } from 'preact';
import {
    canRetry,
    CardFrame,
    CardState,
    failureText,
    ProgressBar,
    StatePill,
    useKit,
    usePage,
    worded,
    type RequestFailure,
} from '@hryvinskyi/admin-ui';
import type { FeatureRow, Features } from '../domain/model.ts';
import {
    featureBodyOf,
    featureSubtitleOf,
    pillLookOf,
    type FeatureBody,
    type FeatureBodyKind,
    type NumbersState,
} from '../domain/overview-view.ts';

export interface FeaturesCardProps {
    readonly features: Features;
    readonly numbers: NumbersState;
    readonly failure: RequestFailure | null;
    readonly onRetry: () => void;
}

const SKELETON = ['70%', '54%', '62%', '48%', '66%'] as const;

function Feature({ row }: { readonly row: FeatureRow }): JSX.Element {
    const pill = pillLookOf(row.status);
    return (
        <div class="rs-overview__feature">
            <span class="rs-overview__feature-name">
                <span class="rs-overview__feature-label">{row.label}</span>
                <span class="rs-overview__feature-on">{row.on}</span>
            </span>
            <span class="rs-overview__feature-measure">
                {row.bar ? <ProgressBar label={row.text} value={row.met} max={row.total} /> : null}
                <span class="rs-overview__feature-text">{row.text}</span>
            </span>
            <span class="rs-overview__feature-pill">
                <StatePill tone={pill.tone} icon={pill.icon} filled>{row.statusLabel}</StatePill>
            </span>
        </div>
    );
}

export function FeaturesCard({ features, numbers, failure, onRetry }: FeaturesCardProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const subtitle = featureSubtitleOf(numbers, features);
    const bodies: { readonly [K in FeatureBodyKind]: (body: FeatureBody<K>) => JSX.Element } = {
        loading: () => (
            <div class="rs-overview__skeleton" aria-busy="true" aria-label={t('page.loadingLabel')}>
                {SKELETON.map((width) => (
                    <div key={width} class="rs-overview__skeleton-row">
                        <span class="rs-overview__skeleton-name" style={{ width }} />
                        <span class="rs-overview__skeleton-bar" />
                        <span class="rs-overview__skeleton-pill" />
                    </div>
                ))}
            </div>
        ),
        failed: () => (
            <div class="rs-overview__card-state">
                <CardState
                    kind="failed"
                    title={failure === null ? t('overview.features.failedTitle') : failureText(failure, kit, t('overview.features.failedTitle'))}
                    text={t('overview.features.failedText')}
                    action={failure !== null && !canRetry(failure) ? undefined : { onClick: onRetry }}
                />
            </div>
        ),
        empty: (body) => (
            <div class="rs-overview__card-state">
                <CardState kind="empty" icon="visibility_off" title={body.note.title} text={body.note.text} />
            </div>
        ),
        rows: (body) => (
            <>
                {body.rows.map((row) => <Feature key={row.rule} row={row} />)}
                {body.footer === null ? null : <p class="rs-overview__footer">{body.footer}</p>}
            </>
        ),
    };
    return (
        <CardFrame
            icon="travel_explore"
            title={t('overview.features.title')}
            subtitle={typeof subtitle === 'string' ? subtitle : worded(translator, subtitle)}
        >
            {bodyOf(bodies, featureBodyOf(numbers, features))}
        </CardFrame>
    );
}

function bodyOf<K extends FeatureBodyKind>(
    bodies: { readonly [P in FeatureBodyKind]: (body: FeatureBody<P>) => JSX.Element },
    body: FeatureBody<K>,
): JSX.Element {
    return bodies[body.kind](body);
}
