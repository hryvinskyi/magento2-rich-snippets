/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The status card of the "Whole catalog" mode: the status word and its note as the server words them; while a check
 * is on its way, how far it has got with the time left and that the page may be left, else the facts of the last
 * run; and "Check now", drawn only for an admin who may run tests and disabled while a check is on its way.
 */
import type { JSX } from 'preact';
import { Button, Icon, ProgressBar, usePage } from '@hryvinskyi/admin-ui';
import type { CatalogControls } from '../application/use-catalog.ts';
import { isChecking, STATUS_LOOKS } from '../domain/catalog-view.ts';

export interface CatalogStatusCardProps {
    readonly controls: CatalogControls;
    /** Whether the admin may ask for a check. */
    readonly canCheck: boolean;
}

export function CatalogStatusCard({ controls, canCheck }: CatalogStatusCardProps): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const { status, asking } = controls.state;
    const look = STATUS_LOOKS[status.tone];
    const checking = isChecking(status);
    const { progress } = status;
    return (
        <section class="hui-card rs-test__status" data-checking={checking ? 'true' : undefined}>
            <div class="hui-status" data-state={look.state === '' ? undefined : look.state}>
                <div class="hui-status__summary">
                    <span class="hui-status__tile"><Icon name={look.icon} size={24} filled={look.state !== ''} /></span>
                    <div class="hui-status__words">
                        <span class="hui-status__word">{status.title}</span>
                        <span class="hui-status__note">{status.note}</span>
                    </div>
                </div>
            </div>
            {checking ? (
                <div class="rs-test__progress">
                    {progress === null ? null : (
                        <>
                            <div class="rs-test__progress-words">
                                <span>{progress.text}</span>
                                {progress.left === null ? null : <span class="rs-test__progress-left">{progress.left}</span>}
                            </div>
                            <ProgressBar label={t('test.check.progress')} value={progress.percent} max={100} />
                        </>
                    )}
                    <span class="rs-test__progress-leave">{t('test.check.leave')}</span>
                </div>
            ) : (
                <dl class="hui-facts rs-test__facts">
                    {status.facts.map((fact) => (
                        <div key={fact.label} class="hui-facts__item">
                            <dt class="hui-facts__label">{fact.label}</dt>
                            <dd class="hui-facts__value">{translator.n(fact.value)}</dd>
                        </div>
                    ))}
                </dl>
            )}
            <div class="hui-actions rs-test__status-actions">
                {canCheck ? (
                    <Button
                        variant="secondary"
                        icon={checking ? 'progress_activity' : 'play_arrow'}
                        disabled={checking}
                        busy={asking}
                        onClick={controls.checkNow}
                    >
                        {t(checking || asking ? 'test.check.running' : 'test.check.run')}
                    </Button>
                ) : null}
            </div>
        </section>
    );
}
