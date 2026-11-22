/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The status card: whether the snippets are live, invalid somewhere or off, in the server's words, the four facts
 * beside it, and the one action that moves the merchant on — drawn only when the admin may open its screen.
 */
import type { JSX } from 'preact';
import { Icon, LinkButton, usePage } from '@hryvinskyi/admin-ui';
import type { Fact, Status } from '../domain/model.ts';
import { statusLookOf } from '../domain/overview-view.ts';

export interface StatusCardProps {
    readonly status: Status;
    readonly facts: readonly Fact[];
}

export function StatusCard({ status, facts }: StatusCardProps): JSX.Element {
    const { link } = usePage();
    const look = statusLookOf(status.tone);
    const action = status.action === null ? null : link(status.action.link);
    return (
        <section class="hui-card rs-overview__status">
            <div class="hui-status" data-state={look.state}>
                <div class="hui-status__summary">
                    <span class="hui-status__tile"><Icon name={look.icon} size={24} filled /></span>
                    <div class="hui-status__words">
                        <span class="hui-status__word">{status.word}</span>
                        <span class="hui-status__note">{status.note}</span>
                    </div>
                </div>
            </div>
            <dl class="hui-facts rs-overview__facts">
                {facts.map((fact) => (
                    <div key={fact.key} class="hui-facts__item">
                        <dt class="hui-facts__label">{fact.label}</dt>
                        <dd class={fact.warn ? 'hui-facts__value rs-overview__fact--warn' : 'hui-facts__value'}>
                            {fact.value}
                        </dd>
                    </div>
                ))}
            </dl>
            <div class="hui-actions rs-overview__status-actions">
                {status.action === null || action === null ? null : (
                    <LinkButton href={action} variant={look.action} forward={look.action === 'primary'}>
                        {status.action.label}
                    </LinkButton>
                )}
            </div>
        </section>
    );
}
