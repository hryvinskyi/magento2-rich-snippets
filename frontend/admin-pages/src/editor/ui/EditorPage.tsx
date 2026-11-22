/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's page: the header with the way back to the snippet list, the unsaved badge, the switch, Test
 * and Save; then, for a snippet, the tabs Properties and Pages — Properties holds the Snippet and Properties cards
 * beside the editor's side column, Pages the Pages card alone, full width. A building block has no page of its own,
 * so it has no tabs and no Pages card.
 *
 * The page reads the sample pages (those the snippet's conditions reach first), the field catalog, the snippets the
 * edited one may include, and the placement count — all in the store view the editor reads in — and hands them to the
 * cards and, through the editor's view, to the parts other parts of the editor draw into it. The sample pages, the
 * catalog's values on a sample page and the placement count read the catalog for whatever draft is sent, so they are
 * asked for only by an admin who may change snippets; an admin who may only see the module reads the catalog's fields
 * without values, and the editor stays readable. Save and Test are announced through the editor's store for the
 * parts of the editor that perform them, and the page opens the Pages tab when another part asks for it; Save is off
 * while a save is on its way or the Code tab holds text that is not a template. The side column, the Properties
 * card's other views and the message under the header are given by those parts.
 */
import type { ComponentChildren, JSX } from 'preact';
import { useEffect, useState } from 'preact/hooks';
import {
    Badge,
    Button,
    linkTo,
    PageMain,
    PageShell,
    Switch,
    tabAfterKey,
    usePage,
    type ModuleShell,
} from '@hryvinskyi/admin-ui';
import { useEditorState, useRemote } from '../application/use-editor.ts';
import { isUnsaved } from '../domain/draft.ts';
import type { EditorSection, FieldCatalog, IncludeCandidate, Sample } from '../domain/model.ts';
import { editorStore } from '../domain/preview-view.ts';
import { parseTemplate } from '../domain/template-projection.ts';
import { useEditorEnv } from './editor-env.tsx';
import { EditorViewProvider, type EditorView } from './editor-view.tsx';
import { PagesCard } from './PagesCard.tsx';
import { PropertiesCard } from './PropertiesCard.tsx';
import { SnippetCard } from './SnippetCard.tsx';

const SNIPPETS_ROUTE = 'hryvinskyi_richsnippets/snippets/index';

/** What other parts of the editor draw into the page. */
export interface EditorSlots {
    /** The side column beside the cards of the Properties tab. */
    readonly aside?: ComponentChildren | undefined;
    /** Content at the right end of the Properties card's head (a choice of views). */
    readonly propertiesAside?: ComponentChildren | undefined;
    /** A view drawn in place of the Properties card; the card with its builder when left out. */
    readonly propertiesView?: ComponentChildren | undefined;
    /** A message under the header (what a save did). */
    readonly notice?: ComponentChildren | undefined;
    /** A save is on its way. */
    readonly saving?: boolean | undefined;
}

type Tab = EditorSection;
const TABS: readonly Tab[] = ['properties', 'pages'];
const tabId = (tab: Tab): string => `rs-editor-tab-${tab}`;
const panelId = (tab: Tab): string => `rs-editor-panel-${tab}`;

const stateOf = <T,>(remote: { readonly kind: string; readonly value?: T }): 'loading' | 'failed' | 'done' | 'idle' =>
    remote.kind === 'done' ? 'done' : remote.kind === 'failed' ? 'failed' : remote.kind === 'idle' ? 'idle' : 'loading';

export interface EditorPageProps {
    readonly shell: ModuleShell;
    readonly slots?: EditorSlots | undefined;
}

export function EditorPage({ shell, slots }: EditorPageProps): JSX.Element {
    const { translator: { t, n }, link } = usePage();
    const { store, ports, stores, canChange, canTest, report, isNew, section } = useEditorEnv();
    const { saved, draft, preview, typeError, codeInvalid } = useEditorState(store);
    const [tab, setTab] = useState<Tab>(section);
    useEffect(() => store.on('pages', () => setTab('pages')), [store]);
    const readIn = editorStore(draft.storeIds, stores.map((view) => view.id));
    const sampleKinds = draft.isBlock ? [] : draft.pageKinds;
    const sampleConditions = draft.isBlock ? '' : draft.conditions;
    const kindsKey = draft.isBlock ? '*' : draft.pageKinds.join(',');

    const samples = useRemote(
        kindsKey === '' || !canChange ? null : JSON.stringify([kindsKey, sampleConditions, readIn]),
        (signal) => ports.endpoints.samples(sampleKinds, sampleConditions, readIn, signal),
        report,
    );
    const firstSample: Sample | null = samples.kind === 'done' ? (samples.value[0] ?? null) : null;
    const sample = preview?.sample ?? firstSample;
    const catalogKey = samples.kind === 'loading' ? null : `${sample?.ref ?? ''}@${String(readIn)}`;
    const catalog = useRemote<FieldCatalog>(catalogKey, (signal) => ports.endpoints.fields(sample?.ref ?? null, readIn, signal), report);
    const candidates = useRemote<readonly IncludeCandidate[]>(String(draft.id ?? 'new'), (signal) => ports.endpoints.includeCandidates(draft.id, signal), report);
    const placementKey = draft.isBlock || draft.pageKinds.length === 0 || !canChange
        ? null
        : JSON.stringify([draft.pageKinds, draft.conditions, draft.storeIds]);
    const placement = useRemote(
        placementKey,
        (signal) => ports.endpoints.placementCount({ pageKinds: draft.pageKinds, conditions: draft.conditions, storeIds: draft.storeIds }, readIn, signal),
        report,
    );
    const view: EditorView = { samples, catalog: catalog.kind === 'done' ? catalog.value : null, store: readIn };

    const projection = parseTemplate(draft.template);
    const propertyCount = projection.kind === 'tree' ? projection.tree.properties.length : null;
    const unsaved = isUnsaved(saved, draft);
    const back = link(linkTo(SNIPPETS_ROUTE));
    const title = draft.name !== '' ? draft.name : isNew ? t('editor.title.new') : draft.code;

    const actions = (
        <div class="rs-editor__actions">
            {unsaved ? <Badge variant="warn">{t('editor.header.unsaved')}</Badge> : null}
            {draft.isBlock ? null : (
                <Switch
                    id="rs-editor-active"
                    checked={draft.active}
                    label={t('editor.header.switch')}
                    words={{ on: t('editor.switch.on'), off: t('editor.switch.off') }}
                    disabled={!canChange}
                    onChange={(active) => store.change((current) => ({ ...current, active }))}
                />
            )}
            {canTest ? <Button variant="secondary" icon="play_arrow" onClick={() => store.request('test')}>{t('editor.header.test')}</Button> : null}
            {canChange ? (
                <Button variant="primary" icon="save" disabled={codeInvalid} busy={slots?.saving === true} onClick={() => store.request('save')}>
                    {t('editor.header.save')}
                </Button>
            ) : null}
        </div>
    );

    const properties = (
        <div class="rs-editor__columns">
            <div class="rs-editor__main">
                <SnippetCard draft={draft} typeError={typeError} />
                {slots?.propertiesView === undefined ? (
                    <PropertiesCard
                        draft={draft}
                        catalog={catalog.kind === 'done' ? catalog.value : null}
                        catalogState={stateOf(catalog)}
                        candidates={candidates.kind === 'done' ? candidates.value : null}
                        candidatesState={stateOf(candidates)}
                        preview={preview}
                        aside={slots?.propertiesAside}
                    />
                ) : slots.propertiesView}
            </div>
            {slots?.aside === undefined ? null : <aside class="rs-editor__aside">{slots.aside}</aside>}
        </div>
    );

    const tabLabels: { readonly [T in Tab]: { readonly label: string; readonly count: string | null } } = {
        properties: { label: t('editor.tabs.properties'), count: propertyCount === null ? null : n(propertyCount) },
        pages: { label: t('editor.tabs.pages'), count: placement.kind === 'done' ? n(placement.value.matching) : null },
    };

    return (
        <PageShell shell={shell} icon="data_object" actions={actions}>
            <PageMain>
                <EditorViewProvider view={view}>
                <div class="rs-editor" data-unsaved={unsaved ? 'true' : 'false'}>
                    <nav class="rs-editor__trail" aria-label={shell.module}>
                        {back === null ? <span>{shell.page}</span> : <a href={back}>{shell.page}</a>}
                        <span class="rs-editor__trail-sep" aria-hidden="true">›</span>
                        <span class="rs-editor__trail-current" aria-current="page">{title}</span>
                    </nav>
                    {slots?.notice ?? null}
                    {draft.isBlock ? properties : (
                        <>
                            <div class="rs-editor__tabs" role="tablist" aria-label={t('editor.tabs.label')}>
                                {TABS.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        role="tab"
                                        id={tabId(item)}
                                        class="rs-editor__tab"
                                        aria-selected={tab === item}
                                        aria-controls={panelId(item)}
                                        tabIndex={tab === item ? 0 : -1}
                                        onClick={() => setTab(item)}
                                        onKeyDown={(event) => {
                                            const next = tabAfterKey(TABS, tab, event.key);
                                            const picked = TABS.find((candidate) => candidate === next);
                                            if (picked !== undefined) {
                                                event.preventDefault();
                                                setTab(picked);
                                                document.getElementById(tabId(picked))?.focus();
                                            }
                                        }}
                                    >
                                        {tabLabels[item].label}
                                        {tabLabels[item].count === null ? null : <span class="rs-editor__tab-count">{tabLabels[item].count}</span>}
                                    </button>
                                ))}
                            </div>
                            <div role="tabpanel" id={panelId(tab)} aria-labelledby={tabId(tab)} class="rs-editor__panel">
                                {tab === 'properties' ? properties : <PagesCard draft={draft} placement={placement} />}
                            </div>
                        </>
                    )}
                </div>
                </EditorViewProvider>
            </PageMain>
        </PageShell>
    );
}
