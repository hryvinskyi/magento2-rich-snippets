/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview with what its components read: the page's wording in the admin's locale (the kit's), and the
 * addresses of the screens the admin may open.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { createPageTranslator, PageProvider, useKit, type Logger } from '@hryvinskyi/admin-ui';
import type { OverviewDocument } from '../adapters/overview-document.ts';
import type { OverviewEndpoints } from '../adapters/overview-endpoints.ts';
import { OverviewPage } from './OverviewPage.tsx';

export interface OverviewIslandProps {
    readonly document: OverviewDocument;
    readonly endpoints: OverviewEndpoints;
    readonly logger: Logger;
}

export function OverviewIsland({ document, endpoints, logger }: OverviewIslandProps): JSX.Element {
    const { locale } = useKit();
    const page = useMemo(() => ({
        translator: createPageTranslator(locale, document.phrases, logger),
        link: document.link,
    }), [locale, document, logger]);
    return (
        <PageProvider page={page}>
            <OverviewPage
                shell={document.shell}
                stores={document.stores}
                data={document.data}
                endpoints={endpoints}
                logger={logger}
            />
        </PageProvider>
    );
}
