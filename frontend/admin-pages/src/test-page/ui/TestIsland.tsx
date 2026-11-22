/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page with what its components read: the page's wording in the admin's locale (the kit's), the addresses
 * of the screens the admin may open, the store views, and whether the admin may run tests.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { createPageTranslator, PageProvider, useKit } from '@hryvinskyi/admin-ui';
import type { TestDocument } from '../adapters/test-document.ts';
import type { TestPorts } from './TestPage.tsx';
import { TestPage } from './TestPage.tsx';

export interface TestIslandProps {
    readonly document: TestDocument;
    readonly ports: TestPorts;
}

export function TestIsland({ document, ports }: TestIslandProps): JSX.Element {
    const { locale } = useKit();
    const page = useMemo(() => ({
        translator: createPageTranslator(locale, document.phrases, ports.logger),
        link: document.link,
    }), [locale, document, ports.logger]);
    return (
        <PageProvider page={page}>
            <TestPage
                shell={document.shell}
                data={document.data}
                stores={document.stores}
                ports={ports}
                canTest={document.allows('test')}
            />
        </PageProvider>
    );
}
