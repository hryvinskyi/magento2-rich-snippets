/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page with what its components read: the page's wording in the admin's locale (the kit's), the
 * addresses of the screens the admin may open, and whether the admin may add templates.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { createPageTranslator, PageProvider, useKit } from '@hryvinskyi/admin-ui';
import type { LibraryDocument } from '../adapters/library-document.ts';
import type { LibraryPorts } from '../application/use-library.ts';
import { LibraryPage } from './LibraryPage.tsx';

export interface LibraryIslandProps {
    readonly document: LibraryDocument;
    readonly ports: LibraryPorts;
}

export function LibraryIsland({ document, ports }: LibraryIslandProps): JSX.Element {
    const { locale } = useKit();
    const page = useMemo(() => ({
        translator: createPageTranslator(locale, document.phrases, ports.logger),
        link: document.link,
    }), [locale, document, ports.logger]);
    return (
        <PageProvider page={page}>
            <LibraryPage shell={document.shell} data={document.data} ports={ports} canChange={document.allows('snippets')} />
        </PageProvider>
    );
}
