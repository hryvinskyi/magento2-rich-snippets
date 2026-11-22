/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One template of the library: its glyph, name and type, what it describes, where it is output and what Google does
 * with it — a tick for a result Google shows, a dash for none, a warning for one it stopped showing — and at its foot
 * whether the shop has it (*Added*, then *Add again*) or *Add*. The add buttons are drawn only for an admin who may
 * change snippets.
 */
import type { JSX } from 'preact';
import { Badge, Button, Icon, usePage } from '@hryvinskyi/admin-ui';
import type { GoogleStatus, LibraryTemplate } from '../domain/model.ts';

/** The glyph of each Google status, filled where the design fills it. */
export const GOOGLE_LOOK = {
    ok: { icon: 'check_circle', filled: true },
    none: { icon: 'remove', filled: false },
    gone: { icon: 'warning', filled: true },
} as const satisfies Record<GoogleStatus, { readonly icon: string; readonly filled: boolean }>;

export interface TemplateCardProps {
    readonly template: LibraryTemplate;
    readonly canChange: boolean;
    readonly onAdd: () => void;
}

export function TemplateCard({ template, canChange, onAdd }: TemplateCardProps): JSX.Element {
    const { t } = usePage().translator;
    const look = GOOGLE_LOOK[template.google.status];
    return (
        <li class="rs-library__item">
            <article class="rs-library__template" aria-label={template.name}>
                <div class="rs-library__template-head">
                    <span class="rs-library__template-tile"><Icon name={template.icon} size={20} /></span>
                    <span class="rs-library__template-title">
                        <span class="rs-library__template-name">{template.name}</span>
                        {template.type === '' ? null : <Badge variant="code">{template.type}</Badge>}
                    </span>
                </div>
                <p class="rs-library__template-text">{template.description}</p>
                <div class="rs-library__template-lines">
                    <span class="rs-library__template-line">
                        <Icon name="near_me" size={16} class="rs-library__template-glyph" />
                        {template.where}
                    </span>
                    <span class="rs-library__template-line" data-google={template.google.status}>
                        <Icon name={look.icon} size={16} filled={look.filled} class="rs-library__google-glyph" />
                        {template.google.short}
                    </span>
                </div>
                <div class="rs-library__template-foot">
                    {template.added ? (
                        <span class="hui-badge hui-badge--on"><Icon name="check" size={14} />{t('library.card.added')}</span>
                    ) : null}
                    {!canChange ? null : template.added ? (
                        <Button variant="link" label={t('library.card.addAgainNamed', template.name)} onClick={onAdd}>
                            {t('library.card.addAgain')}
                        </Button>
                    ) : (
                        <Button variant="tertiary" icon="add" label={t('library.card.addNamed', template.name)} onClick={onAdd}>
                            {t('library.card.add')}
                        </Button>
                    )}
                </div>
            </article>
        </li>
    );
}
