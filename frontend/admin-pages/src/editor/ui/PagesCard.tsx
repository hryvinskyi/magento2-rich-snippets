/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Pages card: where the snippet is output. The kinds of page it is output on, each with how many of its pages
 * match; the conditions narrowing them, in the expression builder configured for the kinds picked (its options in the
 * store view the editor reads in); the server's note about conditions asked of pages they are not about; the store
 * views; and the server's sentence about the whole placement with its count.
 *
 * "Only when" is said once: the expression builder heads itself with it, so the card writes it (with "Optional") only
 * where no builder is drawn — no kind picked, kinds with nothing to test, or a builder still loading or failed.
 *
 * Unpicking a kind takes away the conditions only its pages could be tested on.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { CardFrame, FilterChip, FilterChips, Notice, usePage } from '@hryvinskyi/admin-ui';
import { useRemote } from '../application/use-editor.ts';
import type { Draft, PlacementCount } from '../domain/model.ts';
import { canNarrow, COMBINE_TYPE, conditionCodes, EVERY_STORE, prunedConditions, toggledKinds, toggledStores } from '../domain/placement.ts';
import { ConditionsBuilder } from './ConditionsBuilder.tsx';
import { useEditorEnv } from './editor-env.tsx';
import { useEditorView } from './editor-view.tsx';

export interface PagesCardProps {
    readonly draft: Draft;
    /** How many pages the placement matches, as the page last counted it. */
    readonly placement: { readonly kind: 'idle' | 'loading' | 'failed' } | { readonly kind: 'done'; readonly value: PlacementCount };
}

export function PagesCard({ draft, placement }: PagesCardProps): JSX.Element {
    const { translator: { t, list } } = usePage();
    const { store, ports, kinds, kindsByCode, conditions, stores, canChange, report } = useEditorEnv();
    const { store: readIn } = useEditorView();
    const picked = draft.pageKinds;
    const narrowable = canNarrow(picked, conditions);
    const configKey = picked.length === 0 || !narrowable ? null : `${picked.join(',')}@${String(readIn)}`;
    const config = useRemote(configKey, (signal) => ports.endpoints.builderConfig(picked, readIn, signal), report);
    const counts = placement.kind === 'done' ? placement.value : null;
    const pickedPhrases = useMemo(() => picked.map((code) => kindsByCode.get(code)?.phrase ?? code), [picked, kindsByCode]);

    const toggleKind = (code: string): void => store.change((current) => {
        const pageKinds = toggledKinds(current.pageKinds, code, kinds);
        return {
            ...current,
            pageKinds,
            conditions: prunedConditions(current.conditions, COMBINE_TYPE, conditionCodes(pageKinds, conditions)),
        };
    });
    const toggleStore = (id: number): void => store.change((current) => ({ ...current, storeIds: toggledStores(current.storeIds, id) }));

    return (
        <CardFrame icon="web" title={t('editor.pages.title')} subtitle={t('editor.pages.subtitle')}>
            <div class="hui-card__body rs-editor__pages">
                <section class="rs-editor__pages-part">
                    <h3 class="rs-editor__pages-heading">{t('editor.pages.outputOn')}</h3>
                    <FilterChips label={t('editor.pages.outputOn')}>
                        {kinds.map((kind) => {
                            const count = counts?.kinds.get(kind.code);
                            return (
                                <FilterChip
                                    key={kind.code}
                                    label={kind.label}
                                    icon={kind.icon}
                                    pressed={picked.includes(kind.code)}
                                    disabled={!canChange}
                                    onToggle={() => toggleKind(kind.code)}
                                    {...(count === undefined ? {} : { count: count.matching })}
                                />
                            );
                        })}
                    </FilterChips>
                    {picked.length === 0 ? <Notice tone="warning">{t('editor.pages.noKind')}</Notice> : null}
                </section>
                <section class="rs-editor__pages-part">
                    {config.kind === 'done' ? null : (
                        <h3 class="rs-editor__pages-heading">
                            {t('editor.pages.onlyWhen')} <span class="rs-editor__tag">{t('editor.pages.optional')}</span>
                        </h3>
                    )}
                    {picked.length === 0 ? <p class="rs-editor__muted">{t('editor.pages.pickKind')}</p> : null}
                    {picked.length > 0 && !narrowable ? <p class="rs-editor__muted">{t('editor.pages.nothingToNarrow', list(pickedPhrases))}</p> : null}
                    {config.kind === 'loading' ? <p class="rs-editor__muted" role="status">{t('editor.pages.builderLoading')}</p> : null}
                    {config.kind === 'failed' ? <Notice tone="error">{t('editor.pages.builderFailed')}</Notice> : null}
                    {config.kind === 'done' ? (
                        <ConditionsBuilder
                            widgets={ports.widgets}
                            config={config.value}
                            value={draft.conditions}
                            label={t('editor.pages.onlyWhen')}
                            loadingText={t('editor.pages.builderLoading')}
                            failedText={t('editor.pages.builderFailed')}
                            report={report}
                            onChange={(next) => store.change((current) => (current.conditions === next ? current : { ...current, conditions: next }))}
                        />
                    ) : null}
                    {counts?.asked === null || counts === null ? null : <Notice tone="info">{counts.asked}</Notice>}
                </section>
                <section class="rs-editor__pages-part">
                    <h3 class="rs-editor__pages-heading">{t('editor.pages.stores')}</h3>
                    <FilterChips label={t('editor.pages.stores')}>
                        <FilterChip
                            label={t('editor.pages.allStores')}
                            pressed={draft.storeIds.includes(EVERY_STORE)}
                            disabled={!canChange}
                            onToggle={() => toggleStore(EVERY_STORE)}
                        />
                        {stores.map((view) => (
                            <FilterChip
                                key={view.id}
                                label={view.name}
                                pressed={draft.storeIds.includes(view.id)}
                                disabled={!canChange}
                                onToggle={() => toggleStore(view.id)}
                            />
                        ))}
                    </FilterChips>
                </section>
                <section class="rs-editor__sentence" aria-live="polite">
                    {placement.kind === 'loading' ? <p class="rs-editor__muted" role="status">{t('editor.pages.counting')}</p> : null}
                    {placement.kind === 'failed' ? <Notice tone="warning">{t('editor.pages.countFailed')}</Notice> : null}
                    {counts === null ? null : (
                        <>
                            <p class="rs-editor__sentence-text">{counts.sentence}</p>
                            <p class="rs-editor__sentence-count">{counts.count}</p>
                        </>
                    )}
                </section>
            </div>
        </CardFrame>
    );
}
