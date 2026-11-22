/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Templates card: its head with how many templates the library holds and the search, then the group chips with
 * counts that follow the search, and the templates group by group — headed by the group's label while every group is
 * shown; or, when nothing matches, the way out (a blank snippet, or clearing the search); grey cards while the library
 * loads, with the search disabled; the failure with *Try again* (unless trying again cannot help).
 */
import type { JSX } from 'preact';
import {
    Button,
    canRetry,
    CardFrame,
    CardState,
    failureText,
    FilterChip,
    FilterChips,
    Icon,
    LinkButton,
    useKit,
    usePage,
    worded,
    type RequestFailure,
} from '@hryvinskyi/admin-ui';
import { chipKey, chipsOf, sectionsOf, type GroupFilter } from '../domain/filters.ts';
import type { Library, LibraryTemplate } from '../domain/model.ts';
import type { ListState } from '../application/library-state.ts';
import { SearchField } from './SearchField.tsx';
import { TemplateCard } from './TemplateCard.tsx';

const SKELETON = [['46%', '70%'], ['38%', '58%'], ['52%', '64%'], ['42%', '74%'], ['48%', '52%'], ['36%', '66%']] as const;

export interface TemplatesCardProps {
    readonly list: ListState;
    readonly query: string;
    readonly group: GroupFilter;
    readonly canChange: boolean;
    /** The address of a new snippet; null when the admin may not start one. */
    readonly blank: string | null;
    readonly onSearch: (query: string) => void;
    readonly onGroup: (group: GroupFilter) => void;
    readonly onRetry: () => void;
    readonly onAdd: (template: LibraryTemplate) => void;
}

type Status = ListState['status'];

function Templates({ library, props }: { readonly library: Library; readonly props: TemplatesCardProps }): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const sections = sectionsOf(library, props.query, props.group);
    return (
        <>
            <div class="rs-library__chips">
                <FilterChips label={t('library.chips.label')}>
                    {chipsOf(library, props.query).map((chip) => (
                        <FilterChip
                            key={chip.key}
                            label={typeof chip.label === 'string' ? chip.label : worded(translator, chip.label)}
                            count={chip.count}
                            pressed={chip.key === chipKey(props.group)}
                            onToggle={() => props.onGroup(chip.group)}
                        />
                    ))}
                </FilterChips>
            </div>
            {sections.length === 0 ? (
                <div class="rs-library__state">
                    <CardState
                        kind="no-match"
                        icon="search_off"
                        title={t('library.noMatch.title', props.query.trim())}
                        text={t('library.noMatch.text')}
                    />
                    <div class="hui-actions rs-library__state-actions">
                        {props.blank === null ? null : (
                            <LinkButton href={props.blank} variant="tertiary"><Icon name="draft" size={16} />{t('library.noMatch.blank')}</LinkButton>
                        )}
                        <Button variant="link" onClick={() => props.onSearch('')}>{t('library.noMatch.clear')}</Button>
                    </div>
                </div>
            ) : (
                <div class="rs-library__sections">
                    {sections.map((section) => (
                        <section key={section.group.code} class="rs-library__section" aria-label={section.group.label}>
                            {section.heading ? <h4 class="rs-library__group">{section.group.label}</h4> : null}
                            <ul class="rs-library__grid">
                                {section.templates.map((template) => (
                                    <TemplateCard
                                        key={template.code}
                                        template={template}
                                        canChange={props.canChange}
                                        onAdd={() => props.onAdd(template)}
                                    />
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}
        </>
    );
}

export function TemplatesCard(props: TemplatesCardProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t, tn } = translator;
    const { list } = props;
    const failure: RequestFailure | null = list.status === 'failed' ? list.failure : null;
    const subtitles: { readonly [S in Status]: () => string } = {
        ready: () => tn('library.card.subtitle', list.library.templates.length),
        loading: () => t('page.loading'),
        failed: () => t('page.unavailable'),
    };
    const bodies: { readonly [S in Status]: () => JSX.Element } = {
        ready: () => <Templates library={list.library} props={props} />,
        loading: () => (
            <div class="rs-library__grid rs-library__skeleton" aria-busy="true" aria-label={t('page.loadingLabel')}>
                {SKELETON.map(([name, line]) => (
                    <div key={name + line} class="rs-library__skeleton-card">
                        <span class="rs-library__skeleton-tile" />
                        <span class="rs-library__skeleton-name" style={{ width: name }} />
                        <span class="rs-library__skeleton-line" />
                        <span class="rs-library__skeleton-line" style={{ width: line }} />
                    </div>
                ))}
            </div>
        ),
        failed: () => (
            <div class="rs-library__state rs-library__state--failed">
                <CardState
                    kind="failed"
                    title={failure === null ? t('library.failed.title') : failureText(failure, kit, t('library.failed.title'))}
                    text={t('library.failed.text')}
                    action={failure !== null && !canRetry(failure) ? undefined : { onClick: props.onRetry }}
                />
            </div>
        ),
    };
    return (
        <div class="rs-library__card">
            <CardFrame
                icon="dashboard_customize"
                title={t('library.card.title')}
                subtitle={subtitles[list.status]()}
                aside={(
                    <SearchField
                        value={props.query}
                        label={t('library.search.label')}
                        disabled={list.status !== 'ready'}
                        onInput={props.onSearch}
                    />
                )}
            >
                {bodies[list.status]()}
            </CardFrame>
        </div>
    );
}
