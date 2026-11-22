/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadAmd } from './load-amd.mjs';

const { isTimeOfDay } = loadAmd('view/adminhtml/web/js/time-of-day.js');

test('a time on the 24-hour clock with two digits each is a time of day', () => {
    for (const time of ['00:00', '03:00', '09:05', '12:30', '19:59', '23:30', '23:59', ' 03:00 ']) {
        assert.equal(isTimeOfDay(time), true, time);
    }
});

test('anything else is not, as the server refuses it', () => {
    for (const time of ['', '3:00', '03:0', '24:00', '23:60', '3am', '03:00:00', '03.00', '0300', 'ab:cd', '03 : 00']) {
        assert.equal(isTimeOfDay(time), false, time);
    }
});

test('a value that is not text is not a time of day', () => {
    for (const value of [undefined, null, 300, ['03:00'], { time: '03:00' }]) {
        assert.equal(isTimeOfDay(value), false, String(value));
    }
});

test('the module exports a frozen object, so a page cannot replace the predicate', () => {
    const module = loadAmd('view/adminhtml/web/js/time-of-day.js');
    assert.equal(Object.isFrozen(module), true);
});
