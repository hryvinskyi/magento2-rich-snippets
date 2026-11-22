/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page with what its components read: the page's wording in the admin's locale (the kit's), the
 * addresses of the screens the admin may open, the kinds of page, the server's dates in the admin's words, the store
 * views, and whether the admin may change snippets.
 */
import type { JSX } from 'preact';
import { useMemo } from 'preact/hooks';
import { createPageTranslator, PageProvider, useKit } from '@hryvinskyi/admin-ui';
import type { SnippetsDocument } from '../adapters/snippets-document.ts';
import { createDays } from '../adapters/days.ts';
import type { SnippetsPorts } from '../application/use-snippets.ts';
import { SnippetsPage } from './SnippetsPage.tsx';
import { SnippetsEnvProvider, type SnippetsEnv } from './snippets-env.tsx';

export interface SnippetsIslandProps {
    readonly document: SnippetsDocument;
    readonly ports: SnippetsPorts;
    /** The time zone dates are shown in; the browser's when left out. */
    readonly timeZone?: string | undefined;
}

export function SnippetsIsland({ document, ports, timeZone }: SnippetsIslandProps): JSX.Element {
    const { locale } = useKit();
    const page = useMemo(() => ({
        translator: createPageTranslator(locale, document.phrases, ports.logger),
        link: document.link,
    }), [locale, document, ports.logger]);
    const env = useMemo((): SnippetsEnv => ({
        kinds: document.data.kinds,
        kindsByCode: new Map(document.data.kinds.map((kind) => [kind.code, kind])),
        days: createDays(locale, timeZone),
        stores: document.stores,
        canChange: document.allows('snippets'),
    }), [document, locale, timeZone]);
    return (
        <PageProvider page={page}>
            <SnippetsEnvProvider env={env}>
                <SnippetsPage
                    shell={document.shell}
                    listing={document.data.list}
                    outputLine={document.data.outputLine}
                    ports={ports}
                />
            </SnippetsEnvProvider>
        </PageProvider>
    );
}
