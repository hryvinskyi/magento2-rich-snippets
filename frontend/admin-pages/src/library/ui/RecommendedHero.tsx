/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The first visit's offer: the snippets most shops need, each with its glyph, name and what it covers, and one button
 * that adds them all with the building blocks they share, switched off.
 */
import type { JSX } from 'preact';
import { Button, Icon, usePage } from '@hryvinskyi/admin-ui';
import type { LibraryTemplate } from '../domain/model.ts';

export interface RecommendedHeroProps {
    readonly templates: readonly LibraryTemplate[];
    readonly busy: boolean;
    readonly onAdd: () => void;
}

export function RecommendedHero({ templates, busy, onAdd }: RecommendedHeroProps): JSX.Element {
    const { t } = usePage().translator;
    return (
        <section class="hui-card rs-library__hero" aria-labelledby="rs-library-hero-title">
            <div class="rs-library__hero-words">
                <h2 id="rs-library-hero-title" class="rs-library__hero-title">{t('library.hero.title')}</h2>
                <p class="rs-library__hero-text">{t('library.hero.text')}</p>
                <div class="rs-library__hero-actions">
                    <Button variant="primary" iconEnd="arrow_forward" busy={busy} onClick={onAdd}>
                        {busy ? t('library.hero.adding') : t('library.hero.add')}
                    </Button>
                </div>
            </div>
            <ol class="rs-library__set">
                {templates.map((template) => (
                    <li key={template.code} class="rs-library__set-item">
                        <span class="rs-library__set-tile"><Icon name={template.icon} size={18} /></span>
                        <span class="rs-library__set-words">
                            <span class="rs-library__set-name">{template.name}</span>
                            <span class="rs-library__set-summary">{template.summary}</span>
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
