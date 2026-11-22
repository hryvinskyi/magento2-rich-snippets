/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { stateData } from '../../test/fixtures.ts';
import { parseOverviewData } from '../adapters/overview-data.ts';
import { initialOverview, reduceOverview } from './overview-state.ts';

const healthy = parseOverviewData(stateData('healthy'), '$');
const deutsch = parseOverviewData(stateData('deutsch'), '$');

describe('the Overview\'s state', () => {
    it('opens on the store view of the first data with its numbers in', () => {
        assert.deepEqual(initialOverview(healthy), { store: null, shown: healthy, numbers: 'ready', failure: null });
    });

    it('keeps showing the last Overview while the store view picked loads', () => {
        const picked = reduceOverview(initialOverview(healthy), { type: 'storeChosen', store: 2 });

        assert.equal(picked.store, 2);
        assert.equal(picked.numbers, 'loading');
        assert.equal(picked.shown, healthy);
    });

    it('replaces the whole Overview with the answer', () => {
        const picked = reduceOverview(initialOverview(healthy), { type: 'storeChosen', store: 2 });

        const loaded = reduceOverview(picked, { type: 'loaded', data: deutsch });

        assert.deepEqual(loaded, { store: 2, shown: deutsch, numbers: 'ready', failure: null });
    });

    it('keeps the last Overview after a refusal and says why, until it is asked again', () => {
        const failure = { kind: 'network', reason: null } as const;
        const failed = reduceOverview(reduceOverview(initialOverview(healthy), { type: 'storeChosen', store: 2 }), { type: 'failed', failure });

        assert.deepEqual([failed.numbers, failed.failure, failed.shown], ['failed', failure, healthy]);
        assert.deepEqual(reduceOverview(failed, { type: 'retried' }).numbers, 'loading');
        assert.equal(reduceOverview(failed, { type: 'retried' }).failure, null);
    });
});
