/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page: the module's header with *Blank snippet* (drawn only for an admin who may start one), the module
 * navigation, then the message about the last add, the recommended set while one of it is missing (only for an admin
 * who may add), the Templates card, and beside it how templates work and what to do for a type the library does not
 * hold; the add drawer over it all while it is open.
 */
import type { JSX } from 'preact';
import { Columns, Icon, LinkButton, linkTo, PageMain, PageShell, usePage, type ModuleShell } from '@hryvinskyi/admin-ui';
import { recommendedSetOf } from '../domain/add-view.ts';
import type { LibraryData } from '../domain/model.ts';
import { useLibrary, type LibraryPorts } from '../application/use-library.ts';
import { AddDrawer } from './AddDrawer.tsx';
import { LibraryToast } from './LibraryToast.tsx';
import { RecommendedHero } from './RecommendedHero.tsx';
import { HowPanel, NotInLibraryPanel } from './SidePanels.tsx';
import { TemplatesCard } from './TemplatesCard.tsx';

const NEW_SNIPPET = 'hryvinskyi_richsnippets/snippets/new';

export interface LibraryPageProps {
    readonly shell: ModuleShell;
    readonly data: LibraryData;
    readonly ports: LibraryPorts;
    /** Whether the admin may add templates. */
    readonly canChange: boolean;
}

export function LibraryPage({ shell, data, ports, canChange }: LibraryPageProps): JSX.Element {
    const { translator, link } = usePage();
    const { t } = translator;
    const controls = useLibrary(ports, data, canChange ? data.open : null);
    const { state } = controls;
    const library = state.list.library;
    const blank = link(linkTo(NEW_SNIPPET));
    const set = canChange && state.list.status === 'ready' ? recommendedSetOf(library) : [];
    const drawer = state.drawer;
    const opened = drawer === null ? undefined : library.templates.find((template) => template.code === drawer.code);
    return (
        <PageShell
            shell={shell}
            icon="data_object"
            actions={blank === null ? undefined : (
                <LinkButton href={blank} variant="quiet"><Icon name="draft" size={18} />{t('library.header.blank')}</LinkButton>
            )}
        >
            <PageMain>
                <LibraryToast toast={state.toast} onDismiss={controls.dismissToast} />
                {set.length === 0 ? null : (
                    <RecommendedHero templates={set} busy={state.settingUp} onAdd={controls.addRecommended} />
                )}
                <div class="rs-library">
                    <Columns aside={<><HowPanel /><NotInLibraryPanel blank={blank} /></>}>
                        <TemplatesCard
                            list={state.list}
                            query={state.query}
                            group={state.group}
                            canChange={canChange}
                            blank={blank}
                            onSearch={controls.search}
                            onGroup={controls.chooseGroup}
                            onRetry={controls.retry}
                            onAdd={controls.open}
                        />
                    </Columns>
                </div>
            </PageMain>
            {drawer === null || opened === undefined ? null : (
                <AddDrawer
                    template={opened}
                    drawer={drawer}
                    onChoose={controls.choose}
                    onRetryQuestions={controls.retryQuestions}
                    onAdd={controls.add}
                    onClose={controls.close}
                />
            )}
        </PageShell>
    );
}
