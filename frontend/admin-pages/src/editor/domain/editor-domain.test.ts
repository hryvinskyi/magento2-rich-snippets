/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The builder's pure rules: edits of the tree by path, the rows and chips drawn for it, the row markers read from a
 * preview, the drawer's starting values and checks, the placement's toggles and condition pruning, and the draft
 * kept in step with its template.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isUnsaved, withTemplate, withType } from './draft.ts';
import { isComplete, isUsableName, namedValue, pickableGroups, suggestionsFor, valueOfKind } from './drawer-view.ts';
import { jsonText } from './json-document.ts';
import type { Draft, FieldCatalog } from './model.ts';
import { COMBINE_TYPE, conditionCodes, prunedConditions, toggledKinds, toggledStores } from './placement.ts';
import { initiallyCollapsed, rowMarker, rowsOf, slotOutput, type Words } from './row-view.ts';
import { parseTemplate, printTree } from './template-projection.ts';
import type { Tree } from './template-tree.ts';
import { appendSlot, childrenAt, removeSlot, replaceSlot, slotAt, SlotPathError } from './tree-edits.ts';

const words: Words = {
    t: (key, ...params) => [key, ...params].join(' '),
    tn: (key, count, ...params) => [key, count, ...params].join(' '),
};

const TEMPLATE = `{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "{{product.name | plain | limit:80 | default:'Bag'}}",
  "offers": {
    "@type": "Offer",
    "price": "{{product.final_price}}",
    "priceSpecification": {
      "@if": "product.on_sale",
      "@type": "UnitPriceSpecification",
      "price": "{{product.regular_price}}"
    }
  },
  "image": { "@each": "product.gallery", "@limit": 3, "@value": "{{image.url}}" },
  "review": { "@each": "reviews.latest", "@type": "Review", "reviewBody": "{{review.text}}" },
  "sameAs": ["https://a.example/", "https://b.example/"],
  "brand": { "@include": "brand" },
  "itemCondition": "https://schema.org/NewCondition",
  "custom": null
}`;

function treeOf(template: string): Tree {
    const projection = parseTemplate(template);
    assert.equal(projection.kind, 'tree');
    if (projection.kind !== 'tree') {
        throw new Error('not a tree');
    }
    return projection.tree;
}

const CATALOG: FieldCatalog = {
    groups: [
        {
            code: 'product',
            label: 'Product',
            fields: [
                { path: 'product.name', label: 'Name', attributeCode: null, pageKinds: ['product'], example: 'Field satchel' },
                { path: 'product.final_price', label: 'Price', attributeCode: null, pageKinds: ['product'], example: '89.00' },
            ],
        },
        { code: 'product_attributes', label: 'Product attributes', fields: [{ path: 'product.ean', label: 'EAN', attributeCode: 'ean', pageKinds: ['product'], example: null }] },
        { code: 'image', label: 'Gallery image', fields: [{ path: 'image.url', label: 'Address', attributeCode: null, pageKinds: [], example: null }] },
        { code: 'store', label: 'Store', fields: [{ path: 'store.name', label: 'Name', attributeCode: null, pageKinds: [], example: 'Linden' }] },
    ],
    lists: [{ path: 'product.gallery', label: 'Product › Gallery image', itemName: 'image' }],
    enums: new Map([['itemCondition', ['NewCondition']]]),
    suggestions: new Map([['Product', new Map([['', [{ name: 'sku', required: true, field: 'product.sku' }, { name: 'name', required: true, field: null }]]])]]),
    namedFields: new Map([['Product', new Map([['sku', 'product.sku'], ['color', 'product.color']])]]),
    sample: null,
};

const DRAFT: Draft = {
    id: 3, code: 'product', name: 'Product', type: 'Product', template: TEMPLATE, isBlock: false, active: true,
    pageKinds: ['product'], conditions: '', storeIds: [0], origin: null, updatedAt: null, updatedBy: '',
};

describe('tree edits', () => {
    it('find, replace, remove and append slots by path and print the result', () => {
        const tree = treeOf(TEMPLATE);
        assert.equal(slotAt(tree, [1, 0]).value.kind, 'field');
        const replaced = replaceSlot(tree, [1, 0], { name: 'price', when: null, value: { kind: 'fixed', literal: jsonText('10') } });
        assert.match(printTree(replaced), /"price": "10"/);
        assert.match(printTree(tree), /"price": "\{\{product\.final_price\}\}"/, 'the old tree is unchanged');
        const removed = removeSlot(tree, [1, 1]);
        assert.doesNotMatch(printTree(removed), /priceSpecification/);
        const added = appendSlot(tree, [3], { name: 'author', when: null, value: { kind: 'text', text: 'Ann' } });
        assert.deepEqual(added.path, [3, 1]);
        assert.match(printTree(added.tree), /"reviewBody": "\{\{review\.text\}\}",\n {4}"author": "Ann"/);
        const item = appendSlot(tree, [4], { when: null, value: { kind: 'fixed', literal: jsonText('https://c.example/') } });
        assert.equal(childrenAt(item.tree, [4]).length, 3);
    });

    it('refuses a path that leads nowhere and a nameless slot among properties', () => {
        const tree = treeOf(TEMPLATE);
        assert.throws(() => slotAt(tree, [99]), SlotPathError);
        assert.throws(() => childrenAt(tree, [0]), SlotPathError);
        assert.throws(() => appendSlot(tree, [1], { when: null, value: { kind: 'text', text: 'x' } }), SlotPathError);
    });
});

describe('rows', () => {
    it('flattens the tree with chips for every kind and collapses store lists of objects at first', () => {
        const tree = treeOf(TEMPLATE);
        const collapsed = initiallyCollapsed(tree);
        assert.deepEqual([...collapsed], ['3']);
        const rows = rowsOf(tree, collapsed, { words, catalog: CATALOG, snippetNames: new Map([['brand', 'Brand block']]) });
        const byKey = new Map(rows.map((row) => [row.key, row]));
        assert.deepEqual(byKey.get('0')?.chips.map((chip) => chip.text), [
            'Product › Name', 'editor.filter.plain', 'editor.filter.limit 80', 'editor.chip.else Bag',
        ]);
        assert.deepEqual(byKey.get('1')?.chips.map((chip) => chip.text), ['Offer', 'editor.chip.properties 2']);
        assert.equal(byKey.get('1.1')?.slot.when, 'product.on_sale');
        assert.equal(byKey.get('1.1')?.depth, 1);
        assert.deepEqual(byKey.get('2')?.chips.map((chip) => chip.text), [
            'editor.chip.oneForEach Product › Gallery image', 'editor.chip.newest 3', 'Gallery image › Address',
        ]);
        assert.equal(byKey.get('3')?.collapsed, true);
        assert.equal(byKey.get('3.0'), undefined, 'the rows under a collapsed row are left out');
        assert.deepEqual(byKey.get('4')?.chips.map((chip) => chip.text), ['editor.chip.items 2', 'editor.chip.enteredHere']);
        assert.deepEqual(byKey.get('4.0')?.label, { kind: 'item', number: 1 });
        assert.equal(byKey.get('4')?.addition, 'item');
        assert.deepEqual(byKey.get('5')?.chips.map((chip) => chip.text), ['Brand block', 'editor.chip.snippet']);
        assert.deepEqual(byKey.get('6')?.chips.map((chip) => chip.text), ['editor.chip.quoted https://schema.org/NewCondition', 'editor.chip.schemaValue']);
        assert.deepEqual(byKey.get('7')?.chips.map((chip) => chip.text), ['editor.chip.customCode', 'editor.chip.lines 1']);
    });

    it('marks a row empty or skipped only where the preview output tells', () => {
        const tree = treeOf(TEMPLATE);
        const rows = rowsOf(tree, new Set(), { words, catalog: null, snippetNames: new Map() });
        const row = (key: string) => rows.find((item) => item.key === key) ?? assert.fail(key);
        const output = { name: 'Bag', offers: { price: '10' } };
        assert.equal(rowMarker(tree, row('0'), output), null);
        assert.equal(rowMarker(tree, row('1.0'), output), null);
        assert.equal(rowMarker(tree, row('1.1'), output), 'skipped');
        assert.equal(rowMarker(tree, row('2'), output), 'empty');
        assert.equal(rowMarker(tree, row('4.0'), output), null, 'an item gets no marker');
        assert.equal(rowMarker(tree, row('0'), null), null, 'no marker without a preview');
        assert.deepEqual(slotOutput(tree, [1, 0], output), { kind: 'value', value: '10' });
        assert.equal(slotOutput(tree, [1, 0], { name: 'Bag' }), null, 'an object not output tells nothing');
    });
});

describe('drawer', () => {
    it('starts each kind with its default, keeps the value of the same kind, and checks completeness', () => {
        const defaults = { text: 'A {{b}}', code: '{"@if": "x", "@value": "y"}', when: 'product.on_sale', list: { each: 'product.gallery', item: 'image.url' } };
        const field = { kind: 'field', path: 'product.name', filters: [], fallback: null } as const;
        assert.equal(valueOfKind('field', field, defaults), field);
        assert.deepEqual(valueOfKind('text', field, defaults), { kind: 'text', text: 'A {{b}}' });
        assert.equal(valueOfKind('list', field, defaults).kind, 'list');
        assert.equal(valueOfKind('code', field, defaults).kind, 'code');
        assert.equal(isComplete({ kind: 'field', path: '', filters: [], fallback: null }), false);
        assert.equal(isComplete({ kind: 'snippet', code: '' }), false);
        assert.equal(isComplete({ kind: 'snippet', code: 'brand' }), true);
    });

    it('accepts schema.org names, refuses directives and names taken, and filters the catalog', () => {
        assert.equal(isUsableName('gtin13', []), true);
        assert.equal(isUsableName('@if', []), false);
        assert.equal(isUsableName('9lives', []), false);
        assert.equal(isUsableName('name', ['name']), false);
        assert.deepEqual(pickableGroups(CATALOG, 'ean', ['product'], new Set()).map((group) => group.code), ['product_attributes']);
        assert.deepEqual(pickableGroups(CATALOG, '', ['product'], new Set()).map((group) => group.code), ['product', 'product_attributes', 'store']);
        assert.ok(pickableGroups(CATALOG, '', ['product'], new Set(['image'])).some((group) => group.code === 'image'));
        assert.deepEqual(pickableGroups(CATALOG, '', ['cms'], new Set()).map((group) => group.code), ['store']);
        assert.deepEqual(suggestionsFor(CATALOG, 'Product', '', 'Product', ['name']).map((item) => item.name), ['sku']);
    });

    it('gives a property named by hand the field its name brings, unless a field was picked by hand', () => {
        const empty = { kind: 'field', path: '', filters: [], fallback: null } as const;
        const picked = { kind: 'field', path: 'product.name', filters: [], fallback: null } as const;
        assert.deepEqual(namedValue(CATALOG, 'Product', 'colo', 'color', empty), { ...empty, path: 'product.color' });
        assert.deepEqual(namedValue(CATALOG, 'Product', 'color', 'colour', { ...empty, path: 'product.color' }), empty);
        assert.equal(namedValue(CATALOG, 'Product', 'colo', 'color', picked), picked);
        assert.equal(namedValue(CATALOG, 'Offer', 'colo', 'color', empty), empty);
        const fixed = { kind: 'fixed', literal: jsonText('Tan') } as const;
        assert.equal(namedValue(CATALOG, 'Product', 'colo', 'color', fixed), fixed);
    });
});

describe('placement', () => {
    const vocabulary = new Map([
        ['product', [{ code: 'product_type', label: 'Product type' }]],
        ['cms', [{ code: 'cms_page', label: 'CMS page' }]],
    ]);
    const tree = JSON.stringify({
        type: COMBINE_TYPE, attribute: null, operator: null, value: '1', is_value_processed: null, aggregator: 'all',
        conditions: [
            { type: 'product_type', attribute: null, operator: '!=', value: 'configurable', is_value_processed: null },
            { type: COMBINE_TYPE, aggregator: 'any', value: '1', conditions: [{ type: 'cms_page', operator: '==', value: '2' }] },
        ],
    });

    it('prunes the conditions of a kind no longer picked, and empties a tree with nothing left', () => {
        const kept = prunedConditions(tree, COMBINE_TYPE, conditionCodes(['product'], vocabulary));
        assert.doesNotMatch(kept, /cms_page/);
        assert.match(kept, /product_type/);
        assert.equal(prunedConditions(tree, COMBINE_TYPE, conditionCodes([], vocabulary)), '');
        assert.equal(prunedConditions(tree, COMBINE_TYPE, conditionCodes(['product', 'cms'], vocabulary)), tree, 'unchanged text stays as written');
        assert.equal(prunedConditions('', COMBINE_TYPE, new Set()), '');
        assert.equal(prunedConditions('{oops', COMBINE_TYPE, new Set()), '{oops');
    });

    it('toggles kinds in the listed order and keeps every store view exclusive', () => {
        const kinds = ['home', 'product', 'cms'].map((code) => ({ code, label: code, phrase: code, page: code, icon: 'home' }));
        assert.deepEqual(toggledKinds(['cms'], 'home', kinds), ['home', 'cms']);
        assert.deepEqual(toggledKinds(['home', 'cms'], 'home', kinds), ['cms']);
        assert.deepEqual(toggledStores([0], 2), [2]);
        assert.deepEqual(toggledStores([2], 3), [2, 3]);
        assert.deepEqual(toggledStores([2, 3], 0), [0]);
        assert.deepEqual(toggledStores([2], 2), [0]);
    });
});

describe('draft', () => {
    it('keeps the type and the template root in step and tells what is unsaved', () => {
        const retyped = withType(DRAFT, 'IndividualProduct');
        assert.match(retyped.template, /"@type": "IndividualProduct"/);
        assert.equal(retyped.type, 'IndividualProduct');
        assert.equal(withTemplate(DRAFT, '{"@type": "Offer"}').type, 'Offer');
        assert.equal(withType({ ...DRAFT, template: '[1]' }, 'X').template, '[1]', 'a template the builder cannot read is kept');
        assert.equal(isUnsaved(DRAFT, DRAFT), false);
        assert.equal(isUnsaved(DRAFT, { ...DRAFT, storeIds: [1] }), true);
        assert.equal(isUnsaved(DRAFT, { ...DRAFT, active: false }), true);
    });
});
