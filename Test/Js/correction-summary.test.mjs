/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadAmd } from './load-amd.mjs';

const module = loadAmd('view/adminhtml/web/js/correction-summary.js');

/** The value as plain data of this context: the module's objects come from a context of their own */
const plain = (value) => JSON.parse(JSON.stringify(value));
const fieldsInSection = (...args) => plain(module.fieldsInSection(...args));
const summary = (...args) => plain(module.summary(...args));

test('only the fields of the section count, each once, in their order', () => {
    assert.deepEqual(
        fieldsInSection([
            'hryvinskyi_rich_snippets_check_time',
            'payment_us_express',
            'hryvinskyi_rich_snippets_check_email',
            'hryvinskyi_rich_snippets_check_time',
            'hryvinskyi_rich_snippetsX'
        ], 'hryvinskyi_rich_snippets'),
        ['hryvinskyi_rich_snippets_check_time', 'hryvinskyi_rich_snippets_check_email']
    );
});

test('an error without an ID, or no list at all, counts no field', () => {
    assert.deepEqual(fieldsInSection([null, undefined, 3, ''], 'hryvinskyi_rich_snippets'), []);
    assert.deepEqual(fieldsInSection(undefined, 'hryvinskyi_rich_snippets'), []);
});

test('one field takes the singular summary, more the plural with their count, none no summary', () => {
    assert.deepEqual(summary(1), { form: 'one', count: 1 });
    assert.deepEqual(summary(2), { form: 'many', count: 2 });
    assert.equal(summary(0), null);
    assert.equal(summary(1.5), null);
});

test('the module exports a frozen object, so a page cannot replace it', () => {
    assert.equal(Object.isFrozen(loadAmd('view/adminhtml/web/js/correction-summary.js')), true);
});
