/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The drawer of one finding: its title with how many pages and which snippets, what to do about it, the first pages
 * behind it — each named with a link to its admin screen, its SKU, and "Test", which tests that page in the store
 * view it was checked in — with the server's line saying how many of them are shown and that the download has them
 * all; then the download of every page and, for a finding about products only, the catalog's product list in a new
 * tab.
 * "Test" is drawn only for an admin who may run tests, and each link only for an admin who may open its screen.
 */
import type { JSX } from 'preact';
import {
    Button,
    CardState,
    Drawer,
    failureText,
    LinkButton,
    linkTo,
    Notice,
    useKit,
    usePage,
} from '@hryvinskyi/admin-ui';
import type { FindingDrawer, ItemsState } from '../application/catalog-state.ts';
import { itemLink, PRODUCTS } from '../domain/catalog-view.ts';
import type { Finding, FindingItem, FindingItems } from '../domain/model.ts';

export interface FindingsDrawerProps {
    readonly finding: Finding;
    readonly drawer: FindingDrawer;
    /** Whether the admin may test a page. */
    readonly canTest: boolean;
    /** Reads the pages again after the read failed. */
    readonly onRetry: () => void;
    readonly onDownload: () => void;
    readonly onTest: (item: FindingItem) => void;
    readonly onClose: () => void;
}

function ItemRow({ item, canTest, onTest }: {
    readonly item: FindingItem;
    readonly canTest: boolean;
    readonly onTest: (item: FindingItem) => void;
}): JSX.Element {
    const { translator, link } = usePage();
    const screen = itemLink(item);
    const href = screen === null ? null : link(screen);
    return (
        <div role="row" class="rs-test__item">
            <span role="cell" class="rs-test__item-name">
                {href === null
                    ? <span class="rs-test__item-label">{item.label}</span>
                    : <a class="rs-test__item-label" href={href}>{item.label}</a>}
                {item.group === null ? null : <span class="rs-test__item-group">{item.group}</span>}
            </span>
            <span role="cell" class="rs-test__item-sku">{item.sku ?? ''}</span>
            <span role="cell" class="rs-test__item-test">
                {canTest ? (
                    <Button variant="link" label={translator.t('test.drawer.testNamed', item.label)} onClick={() => onTest(item)}>
                        {translator.t('test.drawer.test')}
                    </Button>
                ) : null}
            </span>
        </div>
    );
}

function Items({ finding, items, canTest, onTest }: {
    readonly finding: Finding;
    readonly items: FindingItems;
    readonly canTest: boolean;
    readonly onTest: (item: FindingItem) => void;
}): JSX.Element {
    const { t } = usePage().translator;
    return (
        <>
            <div role="table" aria-label={t('test.drawer.table')} class="rs-test__items">
                <div role="row" class="rs-test__items-head">
                    <span role="columnheader">{t(finding.allProducts ? 'test.drawer.product' : 'test.drawer.page')}</span>
                    <span role="columnheader">{t('test.drawer.sku')}</span>
                    <span />
                </div>
                {items.items.map((item) => (
                    <ItemRow key={`${item.ref}@${String(item.store)}`} item={item} canTest={canTest} onTest={onTest} />
                ))}
            </div>
            {items.note === null ? null : <p class="rs-test__items-note">{items.note}</p>}
        </>
    );
}

export function FindingsDrawer({ finding, drawer, canTest, onRetry, onDownload, onTest, onClose }: FindingsDrawerProps): JSX.Element {
    const { translator, link } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const products = finding.allProducts ? link(linkTo(PRODUCTS)) : null;
    const bodies: { readonly [S in ItemsState['status']]: (current: ItemsState & { readonly status: S }) => JSX.Element } = {
        loading: () => <p class="rs-test__items-note" aria-busy="true">{t('test.drawer.loading')}</p>,
        ready: (current) => (
            <Items finding={finding} items={current.items} canTest={canTest} onTest={onTest} />
        ),
        failed: (current) => (
            <CardState
                kind="failed"
                title={failureText(current.failure, kit, t('test.drawer.failed'))}
                action={{ onClick: onRetry }}
            />
        ),
    };
    return (
        <Drawer
            title={finding.title}
            aside={<span class="rs-test__drawer-meta">{finding.meta}</span>}
            onClose={onClose}
            returnFocusId={`rs-finding-${finding.key}`}
            actions={(
                <>
                    <Button variant="secondary" icon="download" busy={drawer.downloading} onClick={onDownload}>
                        {t(drawer.downloading ? 'test.drawer.downloading' : 'test.drawer.download')}
                    </Button>
                    {products === null ? null : (
                        <span class="rs-test__drawer-products">
                            <LinkButton href={products} variant="link" external>{t('test.drawer.open')}</LinkButton>
                        </span>
                    )}
                </>
            )}
        >
            <Notice tone="info" muted icon="lightbulb">{finding.fix}</Notice>
            {drawer.downloadFailure === null ? null : (
                <Notice tone="error">{failureText(drawer.downloadFailure, kit, t('test.drawer.downloadFailed'))}</Notice>
            )}
            <div class="rs-test__drawer-items">{itemsBody(bodies, drawer.items)}</div>
        </Drawer>
    );
}

function itemsBody<S extends ItemsState['status']>(
    bodies: { readonly [P in ItemsState['status']]: (current: ItemsState & { readonly status: P }) => JSX.Element },
    items: ItemsState & { readonly status: S },
): JSX.Element {
    return bodies[items.status](items);
}
