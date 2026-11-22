/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's document: its endpoints, the permissions it asks about — changing snippets, testing pages,
 * the module's configuration — and its first data, the snippet with everything drawn around it.
 */
import { parsePageDocument, type PageDocument } from '@hryvinskyi/admin-ui';
import type { EditorData } from '../domain/model.ts';
import { parseEditorData } from './editor-data.ts';

export const EDITOR_ENDPOINTS = Object.freeze([
    'load',
    'save',
    'validate',
    'preview',
    'fields',
    'samples',
    'includeCandidates',
    'builderConfig',
    'placementCount',
] as const);

export type EditorEndpointName = (typeof EDITOR_ENDPOINTS)[number];

export const EDITOR_PERMISSIONS = Object.freeze(['snippets', 'test', 'config'] as const);

export type EditorPermission = (typeof EDITOR_PERMISSIONS)[number];

export type EditorDocument = PageDocument<EditorEndpointName, EditorPermission, EditorData>;

export const parseEditorDocument = (value: unknown): EditorDocument =>
    parsePageDocument(value, { urls: EDITOR_ENDPOINTS, permissions: EDITOR_PERMISSIONS, data: parseEditorData });
