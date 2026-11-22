/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The first screen after the module is installed: nothing is output yet, what a snippet is, the way in through the
 * library (or a blank snippet, for an admin who may add one), and the three steps to a live snippet.
 */
import type { JSX } from 'preact';
import { LinkButton, linkTo, usePage } from '@hryvinskyi/admin-ui';

const LIBRARY = linkTo('hryvinskyi_richsnippets/library/index');
const BLANK = linkTo('hryvinskyi_richsnippets/snippets/new');
const STEPS = ['overview.hero.step1', 'overview.hero.step2', 'overview.hero.step3'] as const;

export function SetupHero(): JSX.Element {
    const { translator: { t }, link } = usePage();
    const library = link(LIBRARY);
    const blank = link(BLANK);
    return (
        <section class="hui-card rs-overview__hero">
            <div class="rs-overview__hero-words">
                <h2 class="rs-overview__hero-title">{t('overview.hero.title')}</h2>
                <p class="rs-overview__hero-text">{t('overview.hero.text')}</p>
                <div class="hui-actions rs-overview__hero-actions">
                    {library === null ? null : (
                        <LinkButton href={library} variant="primary" forward>{t('overview.hero.library')}</LinkButton>
                    )}
                    {blank === null ? null : <LinkButton href={blank} variant="secondary">{t('overview.hero.blank')}</LinkButton>}
                </div>
            </div>
            <ol class="rs-overview__steps">
                {STEPS.map((step, index) => (
                    <li key={step} class="rs-overview__step">
                        <span class="rs-overview__step-number">{index + 1}</span>
                        <span class="rs-overview__step-words">
                            <span class="rs-overview__step-title">{t(`${step}.title`)}</span>
                            <span class="rs-overview__step-text">{t(`${step}.text`)}</span>
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
