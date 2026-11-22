/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadAmd } from './load-amd.mjs';

/**
 * A small page: elements by ID, each with what the mixin asks of it
 */
class FakeElement {
    constructor(id = '') {
        this.id = id;
        this.className = '';
        this.textContent = '';
        this.attributes = {};
        this.children = [];
        this.parent = null;
        this.classes = new Set();
        this.clicks = 0;
        this.classList = { contains: (name) => this.classes.has(name) };
    }

    setAttribute(name, value) {
        this.attributes[name] = value;
    }

    appendChild(child) {
        child.parent = this;
        this.children.push(child);
    }

    replaceChildren(...children) {
        this.children = [];
        children.forEach((child) => this.appendChild(child));
    }

    closest(selector) {
        for (let node = this; node; node = node.parent) {
            if (selector === '.section-config' && node.classes.has('section-config')) {
                return node;
            }
        }
        return null;
    }

    querySelector(selector) {
        return selector.includes('-head') ? this.head ?? null : null;
    }

    click() {
        this.clicks++;
    }
}

function page() {
    const byId = new Map();
    const document = {
        getElementById: (id) => byId.get(id) ?? null,
        createElement: () => new FakeElement()
    };
    const add = (element) => byId.set(element.id, element);
    const messages = new FakeElement('messages');
    add(messages);
    const fieldset = (open) => {
        const node = new FakeElement();
        node.classes.add('section-config');
        if (open) {
            node.classes.add('active');
        }
        node.head = new FakeElement();
        return node;
    };
    const checkFieldset = fieldset(false);
    const outputFieldset = fieldset(true);
    for (const [id, parent] of [
        ['hryvinskyi_rich_snippets_check_time', checkFieldset],
        ['hryvinskyi_rich_snippets_check_email', checkFieldset],
        ['hryvinskyi_rich_snippets_output_position', outputFieldset]
    ]) {
        const field = new FakeElement(id);
        parent.appendChild(field);
        add(field);
    }

    return { document, messages, checkFieldset, outputFieldset };
}

/**
 * The mixin applied to a recording widget factory, on a form of the settings page
 */
function mount(hasSection = true) {
    const scene = page();
    const handlers = [];
    let defined = null;
    const $ = {
        mage: {},
        widget(name, base, prototype) {
            defined = { name, base, prototype };
            $.mage.validation = defined;
            return defined;
        }
    };
    const summaries = loadAmd('view/adminhtml/web/js/correction-summary.js');
    const mixin = loadAmd('view/adminhtml/web/js/validation/correction-summary-mixin.js', {
        jquery: $,
        'mage/translate': (text) => `translated: ${text}`,
        'Hryvinskyi_RichSnippets/js/correction-summary': summaries
    });
    const base = { name: 'the platform validation widget' };
    const returned = mixin(base);
    const form = {
        0: { ownerDocument: scene.document },
        find: () => ({ length: hasSection ? 1 : 0 }),
        on: (event, handler) => handlers.push({ event, handler })
    };
    let superCalled = 0;
    defined.prototype._create.call({ element: form, _super: () => superCalled++ });

    return { ...scene, defined, base, returned, handlers, superCalled };
}

const error = (id) => ({ element: { id } });

test('the mixin extends the platform widget and hands the extended one back', () => {
    const { defined, base, returned, superCalled } = mount();
    assert.equal(defined.name, 'mage.validation');
    assert.equal(defined.base, base);
    assert.equal(returned, defined);
    assert.equal(superCalled, 1, 'the platform widget is still created');
});

test('a form without the section is left alone', () => {
    assert.deepEqual(mount(false).handlers, []);
});

test('one field in error: the singular summary replaces the messages and its closed fieldset is opened', () => {
    const { handlers, messages, checkFieldset } = mount();
    assert.equal(handlers[0].event, 'invalid-form.validate');

    handlers[0].handler({}, { errorList: [error('hryvinskyi_rich_snippets_check_time')] });

    const text = messages.children[0].children[0].children[0];
    assert.equal(
        text.textContent,
        'translated: The configuration was not saved. One field needs a correction; it is marked below.'
    );
    assert.equal(text.attributes['data-ui-id'], 'messages-message-error');
    assert.equal(messages.children[0].children[0].className, 'message message-error error');
    assert.equal(checkFieldset.head.clicks, 1);
});

test('two fields: the plural summary with the count; an open fieldset is not toggled shut', () => {
    const { handlers, messages, checkFieldset, outputFieldset } = mount();

    handlers[0].handler({}, {
        errorList: [error('hryvinskyi_rich_snippets_check_time'), error('hryvinskyi_rich_snippets_output_position')]
    });

    assert.equal(
        messages.children[0].children[0].children[0].textContent,
        'translated: The configuration was not saved. 2 fields need a correction; they are marked below.'
    );
    assert.equal(checkFieldset.head.clicks, 1);
    assert.equal(outputFieldset.head.clicks, 0);
});

test('a save stopped only by fields of another section says nothing', () => {
    const { handlers, messages } = mount();

    handlers[0].handler({}, { errorList: [error('payment_us_express')] });

    assert.deepEqual(messages.children, []);
});
