/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The page map: one row per kind of page with its page count, a chip per snippet it carries (dashed and muted while
 * the snippet is off), or what the row says when it carries none, and *See one* to test a page of that kind — drawn
 * only when there is one to show and the admin may run tests.
 */
import type { JSX } from 'preact';
import { CardFrame, Icon, LinkButton, usePage } from '@hryvinskyi/admin-ui';
import type { KindRow, KindSnippet } from '../domain/model.ts';

export interface KindsCardProps {
    readonly subtitle: string;
    readonly rows: readonly KindRow[];
}

function SnippetChip({ snippet }: { readonly snippet: KindSnippet }): JSX.Element {
    return (
        <span class={snippet.on ? 'rs-overview__chip' : 'rs-overview__chip rs-overview__chip--off'}>
            <span class="rs-overview__chip-name">{snippet.name}</span>
            {snippet.sub === null ? null : <span class="rs-overview__chip-sub">{snippet.sub}</span>}
        </span>
    );
}

function Kind({ row }: { readonly row: KindRow }): JSX.Element {
    const { translator: { t }, link } = usePage();
    const see = row.see === null ? null : link(row.see);
    return (
        <div class="rs-overview__kind">
            <span class="rs-overview__kind-name">
                <span class="rs-overview__kind-tile"><Icon name={row.icon} size={20} /></span>
                <span class="rs-overview__kind-words">
                    <span class="rs-overview__kind-label">{row.label}</span>
                    <span class="rs-overview__kind-pages">{row.pages}</span>
                </span>
            </span>
            <span class="rs-overview__chips">
                {row.snippets.map((snippet) => <SnippetChip key={snippet.id} snippet={snippet} />)}
                {row.snippets.length === 0 && row.empty !== null ? <span class="rs-overview__none">{row.empty}</span> : null}
            </span>
            <span class="rs-overview__kind-see">
                {see === null ? null : <LinkButton href={see} variant="link" forward>{t('overview.kinds.see')}</LinkButton>}
            </span>
        </div>
    );
}

export function KindsCard({ subtitle, rows }: KindsCardProps): JSX.Element {
    const { t } = usePage().translator;
    return (
        <CardFrame icon="web" title={t('overview.kinds.title')} subtitle={subtitle}>
            {rows.map((row) => <Kind key={row.kind} row={row} />)}
        </CardFrame>
    );
}
