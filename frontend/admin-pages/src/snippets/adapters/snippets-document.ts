/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page's document: its seven endpoints, the one permission it asks about — changing snippets, without
 * which no switch, row menu, *Add from the library* or import is drawn — and its first data, the list of every store
 * view with the kinds of page.
 */
import { parsePageDocument, type PageDocument } from '@hryvinskyi/admin-ui';
import type { SnippetsData } from '../domain/model.ts';
import { parseSnippetsData } from './snippets-data.ts';

export const SNIPPETS_ENDPOINTS = Object.freeze([
    'list',
    'setActive',
    'duplicate',
    'delete',
    'export',
    'importPreview',
    'importCommit',
] as const);

export type SnippetsEndpointName = (typeof SNIPPETS_ENDPOINTS)[number];

export type SnippetsPermission = 'snippets';

export type SnippetsDocument = PageDocument<SnippetsEndpointName, SnippetsPermission, SnippetsData>;

export const parseSnippetsDocument = (value: unknown): SnippetsDocument =>
    parsePageDocument(value, { urls: SNIPPETS_ENDPOINTS, permissions: ['snippets'], data: parseSnippetsData });
