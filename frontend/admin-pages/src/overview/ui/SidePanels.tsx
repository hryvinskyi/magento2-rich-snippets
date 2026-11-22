/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The side column: everything that wants fixing, each item with the screen that fixes it (its link drawn only when
 * the admin may open that screen), or the server's line that nothing does; then where the numbers come from.
 */
import type { JSX } from 'preact';
import { Icon, LinkButton, Panel, usePage } from '@hryvinskyi/admin-ui';
import type { Attention, AttentionItem } from '../domain/model.ts';
import { attentionLookOf } from '../domain/overview-view.ts';

function Item({ item }: { readonly item: AttentionItem }): JSX.Element {
    const { link } = usePage();
    const look = attentionLookOf(item.tone);
    const href = link(item.link);
    return (
        <li class="rs-overview__item">
            <Icon name={look.icon} size={20} filled class={`rs-overview__item-icon rs-overview__item-icon--${look.tone}`} />
            <span class="rs-overview__item-words">
                <span class="rs-overview__item-title">{item.title}</span>
                <span class="rs-overview__item-text">{item.description}</span>
                {href === null ? null : <LinkButton href={href} variant="link" forward>{item.action}</LinkButton>}
            </span>
        </li>
    );
}

export function AttentionPanel({ attention }: { readonly attention: Attention }): JSX.Element {
    const { t, n } = usePage().translator;
    return (
        <section class="hui-panel rs-overview__attention">
            <div class="hui-panel__head rs-overview__attention-head">
                <Icon name="flag" size={18} class="hui-panel__icon" />
                <h3 class="hui-panel__title">{t('overview.attention.title')}</h3>
                {attention.count > 0 ? <span class="rs-overview__count">{n(attention.count)}</span> : null}
            </div>
            {attention.items.length === 0 ? null : (
                <ul class="rs-overview__items">
                    {attention.items.map((item) => <Item key={item.key} item={item} />)}
                </ul>
            )}
            {attention.none === null ? null : (
                <p class="rs-overview__nothing">
                    <Icon name="check_circle" size={20} filled class="rs-overview__nothing-icon" />
                    <span>{attention.none}</span>
                </p>
            )}
        </section>
    );
}

export function SourcePanel(): JSX.Element {
    const { t } = usePage().translator;
    return (
        <Panel title={t('overview.source.title')} icon="info">
            <p class="rs-overview__source">{t('overview.source.check')}</p>
            <p class="rs-overview__source">{t('overview.source.limits')}</p>
        </Panel>
    );
}
