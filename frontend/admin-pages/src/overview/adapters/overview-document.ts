/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/** The Overview's page document: its one endpoint, `data`, and its first state, the Overview of every store view. */
import { parsePageDocument, type PageDocument } from '@hryvinskyi/admin-ui';
import type { OverviewData } from '../domain/model.ts';
import { parseOverviewData } from './overview-data.ts';

export type OverviewEndpointName = 'data';

/** The Overview asks about no permission: everything it draws is a read. */
export type OverviewDocument = PageDocument<OverviewEndpointName, never, OverviewData>;

export const parseOverviewDocument = (value: unknown): OverviewDocument =>
    parsePageDocument(value, { urls: ['data'], permissions: [], data: parseOverviewData });
