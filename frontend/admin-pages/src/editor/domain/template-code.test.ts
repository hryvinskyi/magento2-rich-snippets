/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Code tab's pure modules, pinned by vectors: the colours of a template, every problem the check can name with the
 * line it names, the line and field counts of a valid template (the Product template of the library: 54 lines, 20
 * fields), the fields offered while a field is typed, and a property put back into a template.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { offerFields } from './field-completion.ts';
import type { FieldCatalog } from './model.ts';
import { restoredTemplate } from './property-restore.ts';
import { checkTemplate, type TemplateCheck } from './template-check.ts';
import { fieldCount, lex, templateTokens } from './template-lexer.ts';

const PRODUCT = `{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "{{product.name}}",
  "description": "{{product.short_description | plain | limit:5000}}",
  "sku": "{{product.sku}}",
  "gtin13": "{{product.ean}}",
  "image": {
    "@each": "product.gallery",
    "@value": "{{image.url}}"
  },
  "brand": {
    "@type": "Brand",
    "name": "{{product.manufacturer | default:'Linden & Co.'}}"
  },
  "color": "{{product.color}}",
  "offers": {
    "@type": "Offer",
    "url": "{{product.url}}",
    "price": "{{product.final_price}}",
    "priceCurrency": "{{store.currency}}",
    "priceValidUntil": "{{product.special_to_date}}",
    "priceSpecification": {
      "@if": "product.on_sale",
      "@type": "UnitPriceSpecification",
      "priceType": "https://schema.org/StrikethroughPrice",
      "price": "{{product.regular_price}}",
      "priceCurrency": "{{store.currency}}"
    },
    "availability": "{{product.availability}}",
    "itemCondition": "https://schema.org/NewCondition",
    "hasMerchantReturnPolicy": { "@include": "return-policy" }
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "{{reviews.rating}}",
    "reviewCount": "{{reviews.count}}"
  },
  "review": {
    "@each": "reviews.latest",
    "@limit": 3,
    "@type": "Review",
    "author": {
      "@type": "Person",
      "name": "{{review.nickname}}"
    },
    "datePublished": "{{review.date}}",
    "reviewBody": "{{review.text}}",
    "reviewRating": {
      "@type": "Rating",
      "ratingValue": "{{review.rating}}"
    }
  }
}`;

/** The template with one line replaced, counted from 1. */
const withLine = (text: string, line: number, replacement: string): string =>
    text.split('\n').map((current, index) => (index === line - 1 ? replacement : current)).join('\n');

/** The tokens of a text as `kind:text` pairs. */
const coloured = (text: string): readonly string[] =>
    templateTokens(text).map((token) => `${token.kind}:${text.slice(token.from, token.to)}`);

describe('the colours of a template', () => {
    it('colours a property name, a directive, text with fields, a number and the punctuation', () => {
        const text = '{"name": "Bag {{product.sku}} by {{store.name}}", "@each": "reviews.latest", "@limit": 3, "ok": true}';
        assert.deepEqual(coloured(text), [
            'punctuation:{',
            'key:"name"',
            'punctuation::',
            'string:"Bag ',
            'field:{{product.sku}}',
            'string: by ',
            'field:{{store.name}}',
            'string:"',
            'punctuation:,',
            'directive:"@each"',
            'punctuation::',
            'string:"reviews.latest"',
            'punctuation:,',
            'directive:"@limit"',
            'punctuation::',
            'number:3',
            'punctuation:,',
            'key:"ok"',
            'punctuation::',
            'punctuation:}',
        ]);
    });

    it('colours every directive of the template language as a directive, and other @ keys as names', () => {
        for (const directive of ['@if', '@each', '@as', '@limit', '@value', '@include']) {
            assert.deepEqual(coloured(`{"${directive}": 1}`)[1], `directive:"${directive}"`, directive);
        }
        assert.equal(coloured('{"@type": "Product"}')[1], 'key:"@type"');
    });

    it('keeps an escaped {{ as text, and a field with a quoted }} inside its filter whole', () => {
        assert.deepEqual(coloured('"{{{{ literal }} {{a | default:\'}}\'}}"'), [
            'string:"{{{{ literal }} ',
            'field:{{a | default:\'}}\'}}',
            'string:"',
        ]);
    });

    it('colours a field still being typed to the end of its string, and an unclosed string to the end of its line', () => {
        assert.deepEqual(coloured('{"material": "{{product.m\n}'), [
            'punctuation:{',
            'key:"material"',
            'punctuation::',
            'string:"',
            'field:{{product.m',
            'punctuation:}',
        ]);
    });

    it('leaves words and stray characters plain', () => {
        assert.deepEqual(coloured('[null, false, x, #]'), ['punctuation:[', 'punctuation:,', 'punctuation:,', 'punctuation:,', 'punctuation:]']);
    });

    it('knows the line each piece starts on', () => {
        assert.deepEqual(lex('{\n  "a":\n 1\n}').map((piece) => piece.line), [1, 2, 2, 3, 4]);
    });

    it('counts the fields of the library Product template', () => {
        assert.equal(fieldCount(PRODUCT), 20);
    });
});

describe('the check of a template', () => {
    const problem = (text: string): TemplateCheck => checkTemplate(text);

    it('finds the library Product template valid, with its 54 lines and 20 fields', () => {
        assert.deepEqual(problem(PRODUCT), { kind: 'valid', lines: 54, fields: 20 });
    });

    it('says a comma is missing on the line after the value that lacks it, naming that value\'s line', () => {
        const broken = withLine(PRODUCT, 6, '  "sku": "{{product.sku}}"');
        assert.deepEqual(problem(broken), { kind: 'invalid', problem: { code: 'missing-comma', line: 7, after: 6 } });
    });

    it('names the line a nested value ended on when the comma after it is missing', () => {
        assert.deepEqual(problem('{\n  "a": {\n    "b": 1\n  }\n  "c": 2\n}'), {
            kind: 'invalid',
            problem: { code: 'missing-comma', line: 5, after: 4 },
        });
        assert.deepEqual(problem('[1\n2]'), { kind: 'invalid', problem: { code: 'missing-comma', line: 2, after: 1 } });
    });

    it('says a line is not finished while a field is being typed in it', () => {
        const typing = withLine(PRODUCT, 8, '  "material": "{{product.m');
        assert.deepEqual(problem(typing), { kind: 'invalid', problem: { code: 'unfinished', line: 8 } });
    });

    it('says a line is not finished when a name has no colon or a colon no value before the line ends', () => {
        assert.deepEqual(problem('{\n  "name"\n}'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
        assert.deepEqual(problem('{\n  "name":\n}'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
        assert.deepEqual(problem('{\n  "name":\n  "sku": "x"\n}'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
    });

    it('says the last line is not finished when the text ends after a comma, a colon or an opening bracket', () => {
        assert.deepEqual(problem('{\n  "a": 1,'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
        assert.deepEqual(problem('{\n  "a":'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
        assert.deepEqual(problem('{\n  "a": ['), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
        assert.deepEqual(problem(''), { kind: 'invalid', problem: { code: 'unfinished', line: 1 } });
        assert.deepEqual(problem('{\n  "a'), { kind: 'invalid', problem: { code: 'unfinished', line: 2 } });
    });

    it('says which brace or bracket is never closed, on its own line', () => {
        assert.deepEqual(problem('{\n  "a": {\n    "b": 1\n  }'), { kind: 'invalid', problem: { code: 'unclosed', line: 1, bracket: '{' } });
        assert.deepEqual(problem('{\n  "a": [1,\n 2\n}'), { kind: 'invalid', problem: { code: 'unclosed', line: 2, bracket: '[' } });
    });

    it('says a comma ends a list or an object with nothing after it', () => {
        assert.deepEqual(problem('{\n  "a": 1,\n}'), { kind: 'invalid', problem: { code: 'trailing-comma', line: 2 } });
        assert.deepEqual(problem('[1, 2,\n]'), { kind: 'invalid', problem: { code: 'trailing-comma', line: 1 } });
    });

    it('says what is not JSON at its place on its line', () => {
        const cases: readonly (readonly [string, number])[] = [
            ['{\n  "a": tru\n}', 2],
            ['{\n  "a": 1 #\n}', 2],
            ['{\n  1: 2\n}', 2],
            ['{\n  "a": "\\x"\n}', 2],
            ['{\n  "a": 01\n}', 2],
            ['{"a": 1}\n{', 2],
            ['{\n  "a" 1\n}', 2],
            ['{"a": }', 1],
        ];
        for (const [text, line] of cases) {
            assert.deepEqual(problem(text), { kind: 'invalid', problem: { code: 'unexpected', line } }, text);
        }
    });

    it('agrees with the JSON reader on every vector', () => {
        const texts = [PRODUCT, '[]', '{}', '"x"', '12.5e3', 'null', '{"a": [1, {"b": [true, false]}]}', '{\n"a": 1,\n}', '[1 2]', '{"a": "\\u00e9"}'];
        for (const text of texts) {
            let json = true;
            try {
                JSON.parse(text);
            } catch {
                json = false;
            }
            assert.equal(problem(text).kind === 'valid', json, text);
        }
    });
});

const CATALOG: FieldCatalog = {
    groups: [
        {
            code: 'product',
            label: 'Product',
            fields: [
                { path: 'product.name', label: 'Name', attributeCode: 'name', pageKinds: ['product'], example: null },
                { path: 'product.manufacturer', label: 'Manufacturer', attributeCode: 'manufacturer', pageKinds: ['product'], example: null },
                { path: 'product.material', label: 'Material', attributeCode: 'material', pageKinds: ['product'], example: null },
                { path: 'product.mpn', label: 'MPN', attributeCode: 'mpn', pageKinds: ['product'], example: null },
                { path: 'product.ean', label: 'GTIN', attributeCode: 'ean', pageKinds: ['product'], example: null },
            ],
        },
        {
            code: 'store',
            label: 'Store',
            fields: [{ path: 'store.name', label: 'Store name', attributeCode: null, pageKinds: [], example: null }],
        },
    ],
    lists: [],
    enums: new Map(),
    suggestions: new Map(),
    namedFields: new Map(),
    sample: null,
};

describe('the fields offered while a field is typed', () => {
    it('offers every field right after two braces, each closing the expression', () => {
        const text = '{"name": "{{';
        const offer = offerFields(CATALOG, text, text.length);
        assert.deepEqual(offer?.from, text.length);
        assert.deepEqual(offer?.items.map((item) => item.value), ['product.name}}', 'product.manufacturer}}', 'product.material}}', 'product.mpn}}', 'product.ean}}', 'store.name}}']);
        assert.deepEqual(offer?.items[0], { value: 'product.name}}', label: 'product.name', detail: 'Name' });
    });

    it('narrows as the path is typed, the paths that start with it first', () => {
        const text = '"{{product.m';
        const offer = offerFields(CATALOG, text, text.length);
        assert.deepEqual(offer?.items.map((item) => item.label), ['product.manufacturer', 'product.material', 'product.mpn']);
        assert.equal(offer?.from, 3);
        const byLabel = offerFields(CATALOG, '"{{ gtin', 8);
        assert.deepEqual(byLabel?.items.map((item) => item.label), ['product.ean']);
        assert.equal(byLabel?.from, 4);
    });

    it('replaces the whole path at the caret and does not close an expression already closed', () => {
        const text = '"{{product.na}}"';
        const offer = offerFields(CATALOG, text, 12);
        assert.equal(offer?.from, 3);
        assert.equal(offer?.to, 13);
        assert.deepEqual(offer?.items.map((item) => item.value), ['product.name']);
    });

    it('offers nothing outside an open expression, without a catalog, or when nothing matches', () => {
        assert.equal(offerFields(CATALOG, '"{{product.name}} by ', 21), null);
        assert.equal(offerFields(CATALOG, '"plain', 6), null);
        assert.equal(offerFields(CATALOG, '"{{\n', 4), null);
        assert.equal(offerFields(null, '"{{', 3), null);
        assert.equal(offerFields(CATALOG, '"{{zzz', 6), null);
        assert.equal(offerFields(CATALOG, '"{{a | ', 7), null);
    });
});

describe('a property put back into a template', () => {
    const missing = '{\n  "@context": "https://schema.org",\n  "@type": "Product",\n  "name": "{{product.name}}",\n  "aggregateRating": {\n    "@type": "AggregateRating"\n  }\n}';

    it('stands before the first property it comes before in the template the check took it from', () => {
        const restored = restoredTemplate(missing, 'offers', '{"@type": "Offer", "price": "{{product.final_price}}"}', ['aggregateRating', 'review']);
        assert.equal(restored, [
            '{',
            '  "@context": "https://schema.org",',
            '  "@type": "Product",',
            '  "name": "{{product.name}}",',
            '  "offers": {',
            '    "@type": "Offer",',
            '    "price": "{{product.final_price}}"',
            '  },',
            '  "aggregateRating": {',
            '    "@type": "AggregateRating"',
            '  }',
            '}',
        ].join('\n'));
    });

    it('ends its object when none of the properties it comes before is there, and goes inside a path', () => {
        const restored = restoredTemplate(missing, 'aggregateRating.ratingValue', '"{{reviews.rating}}"', ['reviewCount']);
        assert.match(restored ?? '', /"@type": "AggregateRating",\n {4}"ratingValue": "\{\{reviews\.rating\}\}"\n {2}\}/);
    });

    it('takes the place of a property of the same name', () => {
        const restored = restoredTemplate(missing, 'name', '"{{product.meta_title}}"', []);
        assert.match(restored ?? '', /"name": "\{\{product\.meta_title\}\}"/);
        assert.doesNotMatch(restored ?? '', /product\.name/);
    });

    it('goes into the items of a list over a store collection', () => {
        const list = '{\n  "@type": "Product",\n  "review": {\n    "@each": "reviews.latest",\n    "@type": "Review"\n  }\n}';
        assert.match(restoredTemplate(list, 'review.reviewBody', '"{{review.text}}"', []) ?? '', /"@type": "Review",\n {4}"reviewBody": "\{\{review\.text\}\}"/);
    });

    it('puts back an empty text when the check carries no value, or a value that is not JSON', () => {
        assert.match(restoredTemplate(missing, 'sku', null, []) ?? '', /"sku": ""/);
        assert.match(restoredTemplate(missing, 'sku', '{not json', []) ?? '', /"sku": ""/);
    });

    it('puts nothing back through a value that holds no properties, or into a template the builder cannot draw', () => {
        assert.equal(restoredTemplate(missing, 'name.first', '"x"', []), null);
        assert.equal(restoredTemplate(missing, 'offers.price', '"x"', []), null);
        assert.equal(restoredTemplate('{"a": ', 'b', '"x"', []), null);
        assert.equal(restoredTemplate('[1]', 'b', '"x"', []), null);
    });
});
