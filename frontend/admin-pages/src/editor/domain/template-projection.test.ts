/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The template projection pinned by the vectors the server's test compiles too (`Test/_vectors/builder/*.json`):
 * each template reads into the rows it lists, prints into its normal form, and the normal form reads and prints
 * into itself. Beside them, the edge cases of reading a string and a directive.
 */
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { parseTemplate, printTree, readSlot } from './template-projection.ts';
import { readJson } from './json-document.ts';
import { childrenOf, type Slot, type Tree } from './template-tree.ts';

const VECTORS = new URL('../../../../../Test/_vectors/builder/', import.meta.url);

interface Row {
    readonly path: string;
    readonly kind: string;
    readonly when: string | null;
}

interface Vector {
    readonly name: string;
    readonly projection: string;
    readonly template: string;
    readonly printed: string;
    readonly rows: readonly Row[];
}

function vectors(): readonly Vector[] {
    return readdirSync(VECTORS).filter((file) => file.endsWith('.json')).sort().map((file) => {
        const value: unknown = JSON.parse(readFileSync(new URL(file, VECTORS), 'utf8'));
        assert.ok(typeof value === 'object' && value !== null, file);
        const read = (key: string): unknown => Reflect.get(value, key);
        const rows = read('rows');
        assert.ok(Array.isArray(rows), `${file}: rows`);
        return {
            name: String(read('name')),
            projection: String(read('projection')),
            template: String(read('template')),
            printed: String(read('printed')),
            rows: rows.map((row: unknown): Row => {
                assert.ok(typeof row === 'object' && row !== null);
                const when: unknown = Reflect.get(row, 'when');
                return { path: String(Reflect.get(row, 'path')), kind: String(Reflect.get(row, 'kind')), when: typeof when === 'string' ? when : null };
            }),
        };
    });
}

function rowsOf(slots: readonly Slot[], prefix: string, named: boolean): Row[] {
    return slots.flatMap((slot, index) => {
        const key = named && 'name' in slot ? String(slot.name) : String(index + 1);
        const path = prefix === '' ? key : `${prefix}/${key}`;
        const children = childrenOf(slot.value);
        const items = slot.value.kind === 'list' && slot.value.source.kind === 'items';
        return [{ path, kind: slot.value.kind, when: slot.when }, ...(children === null ? [] : rowsOf(children, path, !items))];
    });
}

function treeOf(source: string): Tree {
    const projection = parseTemplate(source);
    assert.equal(projection.kind, 'tree');
    return projection.kind === 'tree' ? projection.tree : { context: null, types: [], properties: [] };
}

describe('the shared vectors', () => {
    for (const vector of vectors()) {
        it(`${vector.name}: reads into its rows and prints its normal form, which prints itself`, () => {
            const projection = parseTemplate(vector.template);
            assert.equal(projection.kind, vector.projection);
            if (projection.kind !== 'tree') {
                assert.equal(vector.printed, vector.template, 'a template the builder cannot draw is kept as it is');
                return;
            }
            assert.deepEqual(rowsOf(projection.tree.properties, '', true), vector.rows);
            assert.equal(printTree(projection.tree), vector.printed);
            assert.equal(printTree(treeOf(vector.printed)), vector.printed);
        });
    }
});

describe('reading a template', () => {
    it('reads text that is not JSON as invalid, and a list or a directive at the root as opaque', () => {
        assert.equal(parseTemplate('{"name": ').kind, 'invalid');
        assert.equal(parseTemplate('[1, 2]').kind, 'opaque');
        assert.equal(parseTemplate('{"@each": "product.gallery"}').kind, 'opaque');
        assert.equal(parseTemplate('{"@type": 3}').kind, 'opaque');
    });

    it('keeps the root\'s @context as written and a type list', () => {
        const tree = treeOf('{"@context": {"@vocab": "https://schema.org/"}, "@type": ["Product", "Car"]}');
        assert.deepEqual(tree.types, ['Product', 'Car']);
        assert.equal(printTree(tree), '{\n  "@context": {\n    "@vocab": "https://schema.org/"\n  },\n  "@type": ["Product", "Car"]\n}');
    });

    it('reads a field with filters and a fallback, and prints its own form', () => {
        const slot = readSlot(readJson('"{{ product.manufacturer | upper | default:\\"Linden & Co.\\" }}"'));
        assert.deepEqual(slot.value, {
            kind: 'field',
            path: 'product.manufacturer',
            filters: [{ name: 'upper', args: [] }],
            fallback: 'Linden & Co.',
        });
    });

    it('keeps a default that is not the last filter, or not a string, among the filters', () => {
        const first = readSlot(readJson('"{{product.name | default:\'x\' | upper}}"'));
        assert.deepEqual(first.value, {
            kind: 'field', path: 'product.name', filters: [{ name: 'default', args: ['\'x\''] }, { name: 'upper', args: [] }], fallback: null,
        });
        const number = readSlot(readJson('"{{product.qty | default:0}}"'));
        assert.deepEqual(number.value, { kind: 'field', path: 'product.qty', filters: [{ name: 'default', args: ['0'] }], fallback: null });
    });

    it('reads a keyword, a literal or a comparison as text with fields, and an unclosed expression as code', () => {
        assert.equal(readSlot(readJson('"{{ not product.on_sale }}"')).value.kind, 'text');
        assert.equal(readSlot(readJson('"{{ \'x\' }}"')).value.kind, 'text');
        assert.equal(readSlot(readJson('"{{ product.name == \'x\' }}"')).value.kind, 'text');
        assert.equal(readSlot(readJson('"{{ product.name"')).value.kind, 'code');
        assert.equal(readSlot(readJson('"{{  }}"')).value.kind, 'code');
    });

    it('reads escaped braces as literal text and writes them escaped again', () => {
        const tree = treeOf('{"code": "{{{{product.name}}"}');
        const first = tree.properties[0];
        assert.ok(first !== undefined && first.value.kind === 'fixed' && first.value.literal.type === 'text');
        assert.equal(first.value.literal.value, '{{product.name}}');
        assert.equal(printTree(tree), '{\n  "code": "{{{{product.name}}"\n}');
    });

    it('keeps a directive the engine refuses in place as code', () => {
        const kinds = (source: string): string => readSlot(readJson(source)).value.kind;
        assert.equal(kinds('{"@include": "faq", "name": "x"}'), 'code', 'an include takes only @if beside it');
        assert.equal(kinds('{"@value": "x", "name": "y"}'), 'code', 'a value takes only @if beside it');
        assert.equal(kinds('{"@limit": 3, "name": "y"}'), 'code', 'a limit needs @each');
        assert.equal(kinds('{"@each": "reviews.latest", "@limit": 0}'), 'code', 'a limit is a positive whole number');
        assert.equal(kinds('{"@each": "reviews.latest | first"}'), 'code', 'a list reads a bare path');
        assert.equal(kinds('{"@each": "reviews.latest", "@value": {"@if": "review.rating", "@value": "x"}}'), 'code');
        assert.equal(kinds('{"@if": "a", "@value": {"@if": "b", "@type": "X"}}'), 'code', 'two conditions on one value');
    });

    it('keeps a key written twice in its first place with its last value', () => {
        const tree = treeOf('{"name": "a", "sku": "b", "name": "c"}');
        assert.equal(printTree(tree), '{\n  "name": "c",\n  "sku": "b"\n}');
    });

    it('keeps numbers as written and a key that reads as a number in its place', () => {
        const tree = treeOf('{"b": 1.50, "2024": 1e3, "a": -0}');
        assert.equal(printTree(tree), '{\n  "b": 1.50,\n  "2024": 1e3,\n  "a": -0\n}');
    });
});
