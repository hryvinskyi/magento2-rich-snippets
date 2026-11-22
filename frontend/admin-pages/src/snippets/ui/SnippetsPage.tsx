/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page: the module's header with *Blank snippet* and *Add from the library* (each drawn only when the
 * admin may open it, *Add* only for an admin who may change snippets), the module navigation, the store-view bar,
 * then the message about the last act, the Snippets card, and beside it how snippets are output and moving snippets
 * between shops; the import drawer over it all while it is open.
 */
import type { JSX } from 'preact';
import {
    Columns,
    Icon,
    LinkButton,
    linkTo,
    PageMain,
    PageShell,
    StoreViewPicker,
    usePage,
    type ModuleShell,
} from '@hryvinskyi/admin-ui';
import { partsOf } from '../domain/list-view.ts';
import type { SnippetRow } from '../domain/model.ts';
import { useImport } from '../application/use-import.ts';
import { useSnippets, type SnippetsPorts } from '../application/use-snippets.ts';
import { shownActive } from '../application/snippets-state.ts';
import type { Listing } from '../domain/model.ts';
import { ImportDrawer } from './ImportDrawer.tsx';
import { MovePanel, OutputPanel } from './SidePanels.tsx';
import { SnippetsCard } from './SnippetsCard.tsx';
import { SnippetsToast } from './SnippetsToast.tsx';
import { storeNameOf, useSnippetsEnv } from './snippets-env.tsx';

const LIBRARY = 'hryvinskyi_richsnippets/library/index';
const NEW_SNIPPET = 'hryvinskyi_richsnippets/snippets/new';

export interface SnippetsPageProps {
    readonly shell: ModuleShell;
    readonly listing: Listing;
    readonly outputLine: string;
    readonly ports: SnippetsPorts;
}

export function SnippetsPage({ shell, listing, outputLine, ports }: SnippetsPageProps): JSX.Element {
    const { translator, link } = usePage();
    const { t } = translator;
    const { stores, canChange } = useSnippetsEnv();
    const controls = useSnippets(ports, listing);
    const { state } = controls;
    const importing = useImport(ports.endpoints, ports.keys, ports.logger, state.store, controls.imported);
    const shown = state.list.listing;
    const parts = partsOf(state.list.status, shown, state.store);
    const blank = link(linkTo(NEW_SNIPPET));
    const library = canChange ? link(linkTo(LIBRARY)) : null;
    const actions = (
        <>
            {blank === null ? null : (
                <LinkButton href={blank} variant="quiet"><Icon name="draft" size={18} />{t('snippets.header.blank')}</LinkButton>
            )}
            {library === null ? null : (
                <LinkButton href={library} variant="primary"><Icon name="add" size={18} />{t('snippets.header.library')}</LinkButton>
            )}
        </>
    );
    const rowProps = (row: SnippetRow) => ({
        active: shownActive(state, row.id, row.active),
        menuOpen: state.menu === row.id,
        confirm: state.confirm !== null && state.confirm.id === row.id ? { includers: state.confirm.includers } : null,
        deleting: state.deleting === row.id,
        onSwitch: (active: boolean): void => controls.setActive(row, active),
        onMenu: (open: boolean): void => controls.toggleMenu(open ? row.id : null),
        onDuplicate: (): void => controls.duplicate(row),
        onExport: (): void => controls.exportOne(row),
        onAskDelete: (): void => controls.askDelete(row),
        onDelete: (): void => controls.remove(row),
        onKeep: (): void => controls.keep(),
    });
    return (
        <PageShell shell={shell} icon="data_object" actions={actions}>
            {parts.storeBar ? (
                <StoreViewPicker
                    id="rs-snippets-store"
                    stores={stores}
                    value={state.store}
                    onChange={controls.chooseStore}
                    label={t('page.store.label')}
                    allLabel={t('page.store.all')}
                    line={(view) => (view === null ? t('snippets.store.allLine') : t('snippets.store.oneLine', view.name))}
                />
            ) : null}
            <PageMain>
                <SnippetsToast toast={state.toast} onDismiss={controls.dismissToast} />
                <div class="rs-snippets">
                    <Columns
                        aside={(
                            <>
                                <OutputPanel outputLine={outputLine} />
                                {canChange ? (
                                    <MovePanel
                                        canExport={parts.exportAll}
                                        canImport={parts.importFile}
                                        onExport={controls.exportAll}
                                        onImport={importing.open}
                                    />
                                ) : null}
                            </>
                        )}
                    >
                        <SnippetsCard
                            status={state.list.status}
                            listing={shown}
                            failure={state.list.status === 'failed' ? state.list.failure : null}
                            filter={state.filter}
                            storeName={storeNameOf(stores, state.store)}
                            chips={parts.chips}
                            rowProps={rowProps}
                            onFilter={controls.chooseFilter}
                            onShowAll={controls.showAll}
                            onRetry={controls.retry}
                        />
                    </Columns>
                </div>
            </PageMain>
            <ImportDrawer
                state={importing.state}
                rows={shown.rows}
                onChoose={importing.choose}
                onPick={importing.pick}
                onCommit={importing.commit}
                onClose={importing.close}
            />
        </PageShell>
    );
}
