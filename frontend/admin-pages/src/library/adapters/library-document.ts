/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page's document: its four endpoints, the one permission it asks about — changing snippets, without
 * which no Add, no *Add again* and no recommended set is drawn — and its first data, the library with the template
 * whose drawer opens first.
 */
import { parsePageDocument, type PageDocument } from '@hryvinskyi/admin-ui';
import type { LibraryData } from '../domain/model.ts';
import { parseLibraryData } from './library-data.ts';

export const LIBRARY_ENDPOINTS = Object.freeze(['list', 'suggestions', 'add', 'addRecommended'] as const);

export type LibraryEndpointName = (typeof LIBRARY_ENDPOINTS)[number];

export type LibraryPermission = 'snippets';

export type LibraryDocument = PageDocument<LibraryEndpointName, LibraryPermission, LibraryData>;

export const parseLibraryDocument = (value: unknown): LibraryDocument =>
    parsePageDocument(value, { urls: LIBRARY_ENDPOINTS, permissions: ['snippets'], data: parseLibraryData });
