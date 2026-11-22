/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page's document: its seven endpoints, the one permission it asks about — running tests, without which no
 * page test, mode switch, "Check now" or "Test" of a page behind a finding is drawn — and its first data (see
 * `test-data.ts`).
 */
import { parsePageDocument, type PageDocument } from '@hryvinskyi/admin-ui';
import type { TestData } from '../domain/model.ts';
import { parseTestData } from './test-data.ts';

export const TEST_ENDPOINTS = Object.freeze([
    'search',
    'run',
    'checkStatus',
    'checkRequest',
    'findings',
    'findingItems',
    'findingsCsv',
] as const);

export type TestEndpointName = (typeof TEST_ENDPOINTS)[number];

export type TestPermission = 'test';

export type TestDocument = PageDocument<TestEndpointName, TestPermission, TestData>;

export const parseTestDocument = (value: unknown): TestDocument =>
    parsePageDocument(value, { urls: TEST_ENDPOINTS, permissions: ['test'], data: parseTestData });
