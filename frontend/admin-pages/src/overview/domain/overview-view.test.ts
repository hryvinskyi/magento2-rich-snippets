/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import type { Features } from './model.ts';
import {
    attentionLookOf,
    featureBodyOf,
    featureSubtitleOf,
    pillLookOf,
    regionsOf,
    statusLookOf,
    storefrontOf,
    storeLineOf,
} from './overview-view.ts';

const ROW = {
    rule: 'merchant_listing',
    label: 'Merchant listing',
    on: 'Product pages',
    met: 182,
    total: 184,
    bar: true,
    text: '182 of 184 products have what Google requires',
    status: 'partly',
    statusLabel: 'Partly',
} as const;

const WITH_ROWS: Features = { subtitle: 'How much…', rows: [ROW], footer: 'From the catalog check…', empty: null };
const EMPTY: Features = {
    subtitle: 'Nothing while the snippets are off.',
    rows: [],
    footer: null,
    empty: { title: 'Nothing to show yet', text: 'Every snippet is off…' },
};

describe('the Overview\'s regions', () => {
    it('shows only the first-visit hero while nothing is added', () => {
        assert.deepEqual(regionsOf('none'), { hero: true, setUp: false });
    });

    it('shows the store bar, the status and the columns once something is added, on or off', () => {
        assert.deepEqual(regionsOf('off'), { hero: false, setUp: true });
        assert.deepEqual(regionsOf('live'), { hero: false, setUp: true });
    });
});

describe('the looks of tones', () => {
    it('draws a live status in the ok state, an invalid one in the error state, and off and none neutral', () => {
        assert.deepEqual(
            (['ok', 'invalid', 'off', 'none'] as const).map((tone) => [statusLookOf(tone).state, statusLookOf(tone).icon]),
            [['ok', 'check_circle'], ['invalid', 'error'], ['', 'visibility_off'], ['', 'visibility_off']],
        );
    });

    it('makes the status action the forward one only while nothing is on', () => {
        assert.equal(statusLookOf('off').action, 'primary');
        assert.equal(statusLookOf('ok').action, 'secondary');
        assert.equal(statusLookOf('invalid').action, 'secondary');
    });

    it('pills a met result ok, a partly met one as an error, and a result Google no longer shows muted', () => {
        assert.deepEqual(pillLookOf('met'), { tone: 'ok', icon: 'check_circle' });
        assert.deepEqual(pillLookOf('partly'), { tone: 'error', icon: 'error' });
        assert.deepEqual(pillLookOf('no_result'), { tone: 'muted', icon: 'remove_circle' });
    });

    it('marks an invalid page as an error, a recommendation as a warning and a note as muted', () => {
        assert.deepEqual(attentionLookOf('bad'), { icon: 'error', tone: 'error' });
        assert.deepEqual(attentionLookOf('warn'), { icon: 'warning', tone: 'warning' });
        assert.deepEqual(attentionLookOf('info'), { icon: 'info', tone: 'muted' });
    });
});

describe('the "What Google can show" card', () => {
    it('holds the rows and the footer once the numbers are in', () => {
        assert.deepEqual(featureBodyOf('ready', WITH_ROWS), { kind: 'rows', rows: [ROW], footer: 'From the catalog check…' });
    });

    it('holds the server\'s note in place of rows it does not have', () => {
        assert.deepEqual(featureBodyOf('ready', EMPTY), { kind: 'empty', note: EMPTY.empty });
    });

    it('holds the loading rows, or the failure, whatever the last numbers held', () => {
        assert.deepEqual(featureBodyOf('loading', WITH_ROWS), { kind: 'loading' });
        assert.deepEqual(featureBodyOf('failed', EMPTY), { kind: 'failed' });
    });

    it('says what the server says under its title, or that the numbers are on their way or not available', () => {
        assert.equal(featureSubtitleOf('ready', WITH_ROWS), 'How much…');
        assert.deepEqual(featureSubtitleOf('loading', WITH_ROWS), { key: 'page.loading', params: [] });
        assert.deepEqual(featureSubtitleOf('failed', WITH_ROWS), { key: 'page.unavailable', params: [] });
    });
});

describe('the store bar and the storefront', () => {
    it('says what the choice means: every store view together, or what the one picked outputs', () => {
        assert.deepEqual(storeLineOf(null), { key: 'overview.store.allLine', params: [] });
        assert.deepEqual(storeLineOf({ name: 'Deutsch' }), { key: 'overview.store.oneLine', params: ['Deutsch'] });
    });

    it('opens the home page of the store view picked, and the default one for every store view', () => {
        const stores = [{ id: 1, home: 'https://en.test/' }, { id: 2, home: 'https://de.test/' }];

        assert.equal(storefrontOf(stores, 2, 'https://default.test/'), 'https://de.test/');
        assert.equal(storefrontOf(stores, null, 'https://default.test/'), 'https://default.test/');
        assert.equal(storefrontOf([{ id: 3, home: '' }], 3, 'https://default.test/'), 'https://default.test/');
    });
});
