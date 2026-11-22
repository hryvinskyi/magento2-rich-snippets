/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets card: its head with how many are on and off and how many building blocks, the chips that filter the
 * list, and under them grey rows while the list loads, the failure with *Try again* (unless trying again cannot help),
 * the first-visit invitation with links to the library and a blank snippet, what a chip leaves out with *Show all
 * snippets*, or the rows.
 */
import type { JSX } from 'preact';
import {
    canRetry,
    CardFrame,
    CardState,
    FilterChip,
    FilterChips,
    failureText,
    LinkButton,
    linkTo,
    useKit,
    usePage,
    worded,
    type RequestFailure,
} from '@hryvinskyi/admin-ui';
import { chipsOf, filterKey, type Filter } from '../domain/filters.ts';
import { cardBodyOf, type CardBody, type CardBodyKind, type ListStatus } from '../domain/list-view.ts';
import type { Listing, SnippetRow as Row } from '../domain/model.ts';
import { subtitleOf } from '../domain/row-view.ts';
import { SnippetRow, type SnippetRowProps } from './SnippetRow.tsx';
import { useSnippetsEnv } from './snippets-env.tsx';

const LIBRARY = 'hryvinskyi_richsnippets/library/index';
const NEW_SNIPPET = 'hryvinskyi_richsnippets/snippets/new';
const SKELETON = [['34%', '70%'], ['28%', '58%'], ['40%', '64%'], ['30%', '74%'], ['36%', '52%'], ['26%', '60%']] as const;

/** What each row is drawn with beyond itself. */
export type RowProps = (row: Row) => Omit<SnippetRowProps, 'row'>;

export interface SnippetsCardProps {
    readonly status: ListStatus;
    readonly listing: Listing;
    readonly failure: RequestFailure | null;
    readonly filter: Filter;
    /** The name of the store view picked; null for every store view. */
    readonly storeName: string | null;
    readonly chips: boolean;
    readonly rowProps: RowProps;
    readonly onFilter: (filter: Filter) => void;
    readonly onShowAll: () => void;
    readonly onRetry: () => void;
}

function bodyOf<K extends CardBodyKind>(
    bodies: { readonly [P in CardBodyKind]: (body: CardBody<P>) => JSX.Element },
    body: CardBody<K>,
): JSX.Element {
    return bodies[body.kind](body);
}

export function SnippetsCard(props: SnippetsCardProps): JSX.Element {
    const { translator, link } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const { kinds, kindsByCode } = useSnippetsEnv();
    const { status, listing, filter } = props;
    const subtitles: { readonly [S in ListStatus]: () => string } = {
        ready: () => subtitleOf(listing.counts, translator),
        loading: () => t('page.loading'),
        failed: () => t('page.unavailable'),
    };
    const library = link(linkTo(LIBRARY));
    const blank = link(linkTo(NEW_SNIPPET));
    const bodies: { readonly [K in CardBodyKind]: (body: CardBody<K>) => JSX.Element } = {
        loading: () => (
            <div class="rs-snippets__skeleton" aria-busy="true" aria-label={t('page.loadingLabel')}>
                {SKELETON.map(([name, line]) => (
                    <div key={name + line} class="rs-snippets__skeleton-row">
                        <span class="rs-snippets__skeleton-tile" />
                        <span class="rs-snippets__skeleton-words">
                            <span class="rs-snippets__skeleton-name" style={{ width: name }} />
                            <span class="rs-snippets__skeleton-line" style={{ width: line }} />
                        </span>
                        <span class="rs-snippets__skeleton-switch" />
                    </div>
                ))}
            </div>
        ),
        failed: () => (
            <div class="rs-snippets__state">
                <CardState
                    kind="failed"
                    title={props.failure === null
                        ? t('snippets.failed.title')
                        : failureText(props.failure, kit, t('snippets.failed.title'))}
                    text={t('snippets.failed.text')}
                    action={props.failure !== null && !canRetry(props.failure) ? undefined : { onClick: props.onRetry }}
                />
            </div>
        ),
        empty: () => (
            <div class="rs-snippets__state">
                <CardState kind="empty" icon="data_object" title={t('snippets.empty.title')} text={t('snippets.empty.text')} />
                {library === null && blank === null ? null : (
                    <div class="hui-actions rs-snippets__state-actions">
                        {library === null ? null : <LinkButton href={library} variant="primary" forward>{t('snippets.empty.library')}</LinkButton>}
                        {blank === null ? null : <LinkButton href={blank} variant="secondary">{t('snippets.empty.blank')}</LinkButton>}
                    </div>
                )}
            </div>
        ),
        noMatch: (body) => (
            <div class="rs-snippets__state">
                <CardState
                    kind="no-match"
                    icon="filter_alt_off"
                    title={t('snippets.noMatch.title')}
                    text={worded(translator, body.text)}
                    action={{ label: t('snippets.noMatch.reset'), onClick: props.onShowAll }}
                />
            </div>
        ),
        rows: (body) => (
            <ul class="rs-snippets__rows">
                {body.rows.map((row) => <SnippetRow key={row.id} row={row} {...props.rowProps(row)} />)}
            </ul>
        ),
    };
    return (
        <div class="rs-snippets__card">
            <CardFrame icon="data_object" title={t('snippets.card.title')} subtitle={subtitles[status]()}>
                {props.chips ? (
                    <div class="rs-snippets__chips">
                        <FilterChips label={t('snippets.chips.label')}>
                            {chipsOf(listing.counts, kinds).map((chip) => (
                                <FilterChip
                                    key={chip.key}
                                    label={typeof chip.label === 'string' ? chip.label : worded(translator, chip.label)}
                                    count={chip.count}
                                    tone={chip.tone}
                                    icon={chip.tone === 'warning' ? 'warning' : undefined}
                                    pressed={chip.key === filterKey(filter)}
                                    onToggle={() => props.onFilter(chip.filter)}
                                />
                            ))}
                        </FilterChips>
                    </div>
                ) : null}
                {bodyOf(bodies, cardBodyOf(status, listing, filter, props.storeName, kindsByCode))}
            </CardFrame>
        </div>
    );
}
