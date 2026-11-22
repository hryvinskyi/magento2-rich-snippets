/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadAmd } from './load-amd.mjs';

/**
 * The mixin with a recording validator and a translation that marks what it translated
 */
function applyMixin() {
    const rules = new Map();
    const $ = { validator: { addMethod: (name, check, message) => rules.set(name, { check, message }) } };
    const $t = (text) => `translated: ${text}`;
    const timeOfDay = loadAmd('view/adminhtml/web/js/time-of-day.js');
    const mixin = loadAmd('view/adminhtml/web/js/validation/time-rule-mixin.js', {
        jquery: $,
        'mage/translate': $t,
        'Hryvinskyi_RichSnippets/js/time-of-day': timeOfDay
    });
    const validation = { name: 'the platform validation widget' };

    return { returned: mixin(validation), validation, rules };
}

test('the mixin hands the validation widget back unchanged', () => {
    const { returned, validation } = applyMixin();
    assert.equal(returned, validation);
});

test('the rule accepts a start time and refuses anything else, with the translated message', () => {
    const { rules } = applyMixin();
    const rule = rules.get('validate-rich-snippets-time');

    assert.ok(rule, 'the rule is registered under its name');
    assert.equal(rule.check('03:00'), true);
    assert.equal(rule.check('3am'), false);
    assert.equal(rule.check(''), false);
    assert.equal(rule.message, 'translated: Enter a time such as 03:00 or 23:30.');
});
