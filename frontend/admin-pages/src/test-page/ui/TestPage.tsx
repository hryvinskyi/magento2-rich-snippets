/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page: the module's header with the switch between its two modes, the module navigation, then either the
 * page bar and what one page outputs beside how it stands against Google's requirements, or where the catalog check
 * stands with its findings beside how the check works; the drawer of a finding over it all while it is open.
 *
 * An admin who may not run tests sees the whole catalog only: no switch, no page bar, no "Check now", no "Test" of a
 * page behind a finding. What the page shows is written into its address, so a reload opens the same.
 */
import type { JSX } from 'preact';
import { useEffect, useState } from 'preact/hooks';
import {
    Columns,
    PageMain,
    PageShell,
    SegmentedControl,
    usePage,
    type Logger,
    type ModuleShell,
    type PageStoreView,
} from '@hryvinskyi/admin-ui';
import { useCatalog } from '../application/use-catalog.ts';
import { usePageSearch } from '../application/use-page-search.ts';
import { usePageTest } from '../application/use-page-test.ts';
import type { FindingsDownloads, PageAddress, Scheduler, TestEndpoints } from '../application/ports.ts';
import { MODES, type FindingItem, type Mode, type TestData } from '../domain/model.ts';
import { addressParams } from '../domain/page-view.ts';
import { CatalogStatusCard } from './CatalogStatusCard.tsx';
import { FindingsCard } from './FindingsCard.tsx';
import { FindingsDrawer } from './FindingsDrawer.tsx';
import { OutputCard } from './OutputCard.tsx';
import { PageBar } from './PageBar.tsx';
import { AskGooglePanel, HowCheckWorksPanel, RequirementsPanel } from './SidePanels.tsx';
import { TestToast } from './TestToast.tsx';

export interface TestPorts {
    readonly endpoints: TestEndpoints;
    readonly downloads: FindingsDownloads;
    readonly scheduler: Scheduler;
    readonly address: PageAddress;
    readonly logger: Logger;
}

export interface TestPageProps {
    readonly shell: ModuleShell;
    readonly data: TestData;
    readonly stores: readonly PageStoreView[];
    readonly ports: TestPorts;
    /** Whether the admin may test a page and ask for a check. */
    readonly canTest: boolean;
}

const MODE_WORDS = { page: 'test.mode.page', catalog: 'test.mode.catalog' } as const satisfies Record<Mode, string>;

/** The glyph before each mode's name in the switch. */
const MODE_ICONS = { page: 'web', catalog: 'inventory' } as const satisfies Record<Mode, string>;

export function TestPage({ shell, data, stores, ports, canTest }: TestPageProps): JSX.Element {
    const { t } = usePage().translator;
    const [mode, setMode] = useState<Mode>(canTest ? data.mode : 'catalog');
    const pageTest = usePageTest(ports.endpoints, ports.logger, data, mode === 'page', canTest);
    const search = usePageSearch(ports.endpoints, ports.scheduler, ports.logger);
    const catalog = useCatalog(ports, {
        status: data.status,
        findings: data.findings,
        store: data.catalogStore,
        opening: data.mode === 'catalog' ? data.finding : null,
    });
    const { page, store } = pageTest.state;
    const { address } = ports;
    useEffect(() => {
        address.show(addressParams(mode, page, store, data.catalogStore));
    }, [address, mode, page, store, data.catalogStore]);

    const testItem = (item: FindingItem): void => {
        catalog.close();
        setMode('page');
        pageTest.testPage({ ref: item.ref, kind: item.kind, label: item.label, path: '' }, item.store);
    };
    const drawer = catalog.state.drawer;
    const opened = drawer === null
        ? undefined
        : catalog.state.findings.table.findings.find((finding) => finding.key === drawer.key);
    return (
        <PageShell
            shell={shell}
            icon="data_object"
            actions={canTest ? (
                <div class="rs-test__modes">
                    <SegmentedControl
                        label={t('test.mode.label')}
                        value={mode}
                        options={MODES.map((value) => ({ value, label: t(MODE_WORDS[value]), icon: MODE_ICONS[value] }))}
                        onChange={(value) => setMode(value === 'page' ? 'page' : 'catalog')}
                    />
                </div>
            ) : undefined}
        >
            <div class="rs-test" data-mode={mode}>
                {mode === 'page' ? (
                    <PageBar controls={pageTest} search={search} stores={stores} />
                ) : null}
                <PageMain>
                    <TestToast toast={catalog.state.toast} onDismiss={catalog.dismissToast} />
                    {mode === 'page' ? (
                        <Columns
                            aside={(
                                <>
                                    <RequirementsPanel run={pageTest.state.run} />
                                    <AskGooglePanel />
                                </>
                            )}
                        >
                            <OutputCard controls={pageTest} />
                        </Columns>
                    ) : (
                        <>
                            <CatalogStatusCard controls={catalog} canCheck={canTest} />
                            <Columns aside={<HowCheckWorksPanel schedule={data.schedule} />}>
                                <FindingsCard controls={catalog} />
                            </Columns>
                        </>
                    )}
                </PageMain>
                {drawer === null || opened === undefined || mode !== 'catalog' ? null : (
                    <FindingsDrawer
                        finding={opened}
                        drawer={drawer}
                        canTest={canTest}
                        onRetry={() => catalog.showPart(drawer.page)}
                        onDownload={() => catalog.download(opened)}
                        onTest={testItem}
                        onClose={catalog.close}
                    />
                )}
            </div>
        </PageShell>
    );
}
