/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The side column: how templates work — a copy is the shop's own, arrives switched off, and stays as it is when the
 * library changes — and what to do for a type the library does not hold, with *Blank snippet* for an admin who may
 * start one.
 */
import type { JSX } from 'preact';
import { Icon, LinkButton, Panel, usePage } from '@hryvinskyi/admin-ui';

export function HowPanel(): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('library.how.title')} icon="info">
            <div class="rs-library__aside-text">
                <p>{t('library.how.copy')}</p>
                <p>{t('library.how.off')}</p>
                <p>{t('library.how.update')}</p>
            </div>
        </Panel>
    );
}

export function NotInLibraryPanel({ blank }: { readonly blank: string | null }): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('library.notIn.title')} icon="draft">
            <p class="rs-library__aside-text">{t('library.notIn.text')}</p>
            {blank === null ? null : (
                <div class="hui-actions">
                    <LinkButton href={blank} variant="tertiary"><Icon name="draft" size={16} />{t('library.notIn.blank')}</LinkButton>
                </div>
            )}
        </Panel>
    );
}
