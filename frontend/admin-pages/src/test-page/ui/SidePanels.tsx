/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The side panels of the Test page.
 *
 * - "Against Google's requirements": one entry per kind of result the page's blocks can have, with its verdict as a
 *   pill, the server's words, and the properties missing — required ones in the error tone, recommended ones plain;
 *   while testing, or when there is nothing to compare, it says so.
 * - "Ask Google itself": what the page test is not, and a link to Google's own test.
 * - "How the check works": what the catalog check builds, what a finding is about, and when it runs by itself.
 */
import type { JSX } from 'preact';
import { LinkButton, Panel, StatePill, usePage } from '@hryvinskyi/admin-ui';
import type { RunState } from '../application/page-test-state.ts';
import type { Feature } from '../domain/model.ts';
import { FEATURE_LOOKS } from '../domain/page-view.ts';

/** Google's own test of a page's rich results. */
export const RICH_RESULTS_TEST = 'https://search.google.com/test/rich-results';

function FeatureEntry({ feature }: { readonly feature: Feature }): JSX.Element {
    const { t } = usePage().translator;
    const look = FEATURE_LOOKS[feature.verdict];
    const required = feature.verdict === 'not_met';
    const missing = required ? feature.missingRequired : feature.verdict === 'met' ? feature.missingRecommended : [];
    return (
        <div class="rs-test__feature">
            <div class="rs-test__feature-head">
                <span class="rs-test__feature-label">{feature.label}</span>
                <StatePill tone={look.tone} icon={look.icon} filled>{t(look.word)}</StatePill>
            </div>
            <span class="rs-test__feature-text">{feature.description}</span>
            {missing.length === 0 ? null : (
                <div class="hui-actions rs-test__chips">
                    {missing.map((property) => (
                        <span
                            key={property}
                            class="hui-badge hui-badge--code rs-test__chip"
                            data-required={required ? 'true' : undefined}
                        >
                            {property}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

export function RequirementsPanel({ run }: { readonly run: RunState }): JSX.Element {
    const { t } = usePage().translator;
    const features = run.status === 'done' ? run.test.features : [];
    const empties: { readonly [S in RunState['status']]: () => string } = {
        idle: () => t('test.req.testing'),
        busy: () => t('test.req.testing'),
        done: () => (run.status === 'done' && run.test.failure !== null ? t('test.req.unread') : t('test.req.nothing')),
        error: () => t('test.req.unread'),
    };
    return (
        <Panel title={t('test.req.title')} icon="fact_check" live>
            {features.length === 0 ? (
                <p class="rs-test__panel-empty">{empties[run.status]()}</p>
            ) : features.map((feature, index) => <FeatureEntry key={`${feature.rule}-${String(index)}`} feature={feature} />)}
        </Panel>
    );
}

export function AskGooglePanel(): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('test.google.title')} icon="travel_explore">
            <p class="rs-test__panel-text">{t('test.google.text')}</p>
            <div class="rs-test__panel-action">
                <LinkButton href={RICH_RESULTS_TEST} variant="tertiary" external>{t('test.google.open')}</LinkButton>
            </div>
        </Panel>
    );
}

export function HowCheckWorksPanel({ schedule }: { readonly schedule: string }): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('test.how.title')} icon="info">
            <p class="rs-test__panel-text">{t('test.how.builds')}</p>
            <p class="rs-test__panel-text">{t('test.how.data')}</p>
            <p class="rs-test__panel-text">{schedule}</p>
        </Panel>
    );
}
