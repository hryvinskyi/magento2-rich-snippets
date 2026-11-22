/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview: the module's header with the live badge and *View storefront*, the module navigation, then — while
 * nothing is added — the first-visit hero alone, or the store-view bar, the status card, the page map with the Google
 * card, and the attention list with where the numbers come from beside them.
 */
import type { JSX } from 'preact';
import {
    Badge,
    Columns,
    LinkButton,
    PageMain,
    PageShell,
    StoreViewPicker,
    usePage,
    worded,
    type Logger,
    type ModuleShell,
    type PageStoreView,
} from '@hryvinskyi/admin-ui';
import type { OverviewEndpoints } from '../adapters/overview-endpoints.ts';
import { useOverview } from '../application/use-overview.ts';
import type { OverviewData } from '../domain/model.ts';
import { regionsOf, storefrontOf, storeLineOf } from '../domain/overview-view.ts';
import { FeaturesCard } from './FeaturesCard.tsx';
import { KindsCard } from './KindsCard.tsx';
import { SetupHero } from './SetupHero.tsx';
import { AttentionPanel, SourcePanel } from './SidePanels.tsx';
import { StatusCard } from './StatusCard.tsx';

export interface OverviewPageProps {
    readonly shell: ModuleShell;
    readonly stores: readonly PageStoreView[];
    readonly data: OverviewData;
    readonly endpoints: OverviewEndpoints;
    readonly logger: Logger;
}

export function OverviewPage({ shell, stores, data, endpoints, logger }: OverviewPageProps): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const { state, chooseStore, retry } = useOverview(endpoints, data, logger);
    const shown = state.shown;
    const regions = regionsOf(shown.state);
    const storefront = storefrontOf(stores, state.store, shell.storefront);
    const actions = (
        <>
            <Badge variant={shown.badge.on ? 'on' : 'off'}>{shown.badge.text}</Badge>
            {storefront === '' ? null : (
                <LinkButton href={storefront} variant="quiet" external>{t('page.storefront')}</LinkButton>
            )}
        </>
    );
    return (
        <PageShell shell={shell} icon="data_object" actions={actions}>
            {regions.setUp ? (
                <StoreViewPicker
                    id="rs-overview-store"
                    stores={stores}
                    value={state.store}
                    onChange={chooseStore}
                    label={t('page.store.label')}
                    allLabel={t('page.store.all')}
                    line={(view) => worded(translator, storeLineOf(view))}
                />
            ) : null}
            <PageMain>
                {regions.hero ? <SetupHero /> : null}
                {regions.setUp ? (
                    <>
                        <StatusCard status={shown.status} facts={shown.facts} />
                        <Columns aside={<><AttentionPanel attention={shown.attention} /><SourcePanel /></>}>
                            <KindsCard subtitle={shown.kinds.subtitle} rows={shown.kinds.rows} />
                            <FeaturesCard
                                features={shown.features}
                                numbers={state.numbers}
                                failure={state.failure}
                                onRetry={retry}
                            />
                        </Columns>
                    </>
                ) : null}
            </PageMain>
        </PageShell>
    );
}
