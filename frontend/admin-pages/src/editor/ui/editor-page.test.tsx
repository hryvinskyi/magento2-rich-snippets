/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor drawn from the document the server prints, with fake ports: the header and its announcements,
 * the rows of the builder, the property drawer for several kinds of value, the Pages tab with the expression builder
 * and the placement sentence, a building block, a blank snippet's suggestions, and the markers a preview brings.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, waitFor, within } from '@testing-library/preact';
import { blankData, blockData, editorData } from '../../test/editor-fixtures.ts';
import { drawEditor } from '../../test/editor-harness.tsx';

afterEach(cleanup);

const drawer = (): HTMLElement => {
    const found = document.querySelector<HTMLElement>('.hui-drawer');
    assert.ok(found !== null, 'the property drawer is open');
    return found;
};

const button = (root: Element, text: string): HTMLElement => {
    const found = [...root.querySelectorAll<HTMLElement>('button')].find((item) => (item.textContent ?? '').includes(text));
    assert.ok(found !== undefined, `a button "${text}"`);
    return found;
};

const PRODUCT = editorData({
    template: '{\n  "@context": "https://schema.org",\n  "@type": "Product",\n  "name": "{{product.name}}",\n  "brand": {"@include": "postal-address"},\n  "offers": {"@type": "Offer", "price": "{{product.final_price}}"}\n}',
});

describe('the header and the builder', () => {
    it('draws the trail, the tabs and a row per property with its chips, reading the catalog for the first sample', async () => {
        const view = drawEditor(PRODUCT);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        const text = view.container.textContent ?? '';
        assert.ok(text.includes('Product'), 'the snippet name in the trail');
        assert.equal(view.container.querySelectorAll('[role="tab"]').length, 2);
        const rows = [...view.container.querySelectorAll('.rs-editor__row')];
        assert.deepEqual(rows.map((row) => row.querySelector('.rs-editor__name')?.textContent), ['name', 'brand', 'offers', 'price']);
        await waitFor(() => assert.ok((rows[0]?.textContent ?? '').includes('Product › Name') || (view.container.querySelector('.rs-editor__row')?.textContent ?? '').includes('Product › Name')));
        await waitFor(() => assert.ok((view.container.querySelectorAll('.rs-editor__row')[1]?.textContent ?? '').includes('Postal address')));
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'fields')?.args, ['product:12', null]);
        assert.ok(view.container.querySelector('.rs-editor__actions .hui-badge') === null, 'nothing is unsaved yet');
    });

    it('shows the unsaved badge after a change, toggles the switch, and announces save and test', async () => {
        const view = drawEditor(PRODUCT);
        const heard: string[] = [];
        view.store.on('save', () => void heard.push('save'));
        view.store.on('test', () => void heard.push('test'));
        fireEvent.input(view.container.querySelector('#rs-editor-name') ?? assert.fail('the name field'), { target: { value: 'Bag' } });
        await waitFor(() => assert.ok((view.container.querySelector('.rs-editor__actions')?.textContent ?? '').includes('editor.header.unsaved')));
        fireEvent.click(view.container.querySelector('[role="switch"]') ?? assert.fail('the switch'));
        assert.equal(view.store.state().draft.active, false);
        fireEvent.click(button(view.container, 'editor.header.save'));
        fireEvent.click(button(view.container, 'editor.header.test'));
        assert.deepEqual(heard, ['save', 'test']);
    });

    it('changes the type in the template as it is typed', () => {
        const view = drawEditor(PRODUCT);
        fireEvent.input(view.container.querySelector('#rs-editor-type') ?? assert.fail('the type field'), { target: { value: 'IndividualProduct' } });
        assert.match(view.store.state().draft.template, /"@type": "IndividualProduct"/);
    });

    it('draws no Save or Test and a locked switch for an admin who may neither change snippets nor test pages', () => {
        const view = drawEditor(PRODUCT, { canChange: false, canTest: false });
        assert.ok(!(view.container.textContent ?? '').includes('editor.header.save'));
        assert.ok(!(view.container.textContent ?? '').includes('editor.header.test'));
        assert.ok(view.container.querySelector('[role="switch"]')?.getAttribute('aria-disabled') === 'true'
            || view.container.querySelector<HTMLButtonElement>('[role="switch"]')?.disabled === true);
    });
});

describe('the property drawer', () => {
    it('turns a field into a fixed value and prints it on Done; Cancel leaves the template as it was', async () => {
        const view = drawEditor(PRODUCT);
        const before = view.store.state().draft.template;
        fireEvent.click(view.container.querySelector('#rs-editor-row-0') ?? assert.fail('the name row'));
        fireEvent.click(within(drawer()).getByLabelText(/editor\.kind\.fixed /));
        fireEvent.input(drawer().querySelector('#rs-editor-value-fixed') ?? assert.fail('the fixed field'), { target: { value: 'Satchel' } });
        fireEvent.click(button(drawer(), 'editor.drawer.cancel'));
        await waitFor(() => assert.ok(document.querySelector('.hui-drawer') === null));
        assert.equal(view.store.state().draft.template, before);

        fireEvent.click(view.container.querySelector('#rs-editor-row-0') ?? assert.fail('the name row'));
        fireEvent.click(within(drawer()).getByLabelText(/editor\.kind\.fixed /));
        fireEvent.input(drawer().querySelector('#rs-editor-value-fixed') ?? assert.fail('the fixed field'), { target: { value: 'Satchel' } });
        fireEvent.click(button(drawer(), 'editor.drawer.done'));
        assert.match(view.store.state().draft.template, /"name": "Satchel"/);
    });

    it('adds a property with a field picked from the catalog, refusing it until it has a name and a field', async () => {
        const view = drawEditor(PRODUCT);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        fireEvent.click(view.container.querySelector('#rs-editor-row-root') ?? assert.fail('the add button'));
        const add = button(drawer(), 'editor.drawer.addButton');
        assert.equal(add.hasAttribute('disabled') || add.getAttribute('aria-disabled') === 'true', true);
        fireEvent.input(drawer().querySelector('#rs-editor-property-name') ?? assert.fail('the name'), { target: { value: 'gtin13' } });
        await waitFor(() => assert.ok(drawer().querySelector('.rs-editor__picker-field')));
        fireEvent.click(button(drawer(), 'EAN'));
        fireEvent.click(button(drawer(), 'editor.drawer.addButton'));
        assert.match(view.store.state().draft.template, /"gtin13": "\{\{product\.ean\}\}"/);
    });

    it('gives a property named by hand the field its name brings', async () => {
        const view = drawEditor(PRODUCT);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        fireEvent.click(view.container.querySelector('#rs-editor-row-root') ?? assert.fail('the add button'));
        await waitFor(() => assert.ok(drawer().querySelector('.rs-editor__suggestions')));
        fireEvent.input(drawer().querySelector('#rs-editor-property-name') ?? assert.fail('the name'), { target: { value: 'material' } });
        fireEvent.click(button(drawer(), 'editor.drawer.addButton'));
        assert.match(view.store.state().draft.template, /"material": "\{\{product\.material\}\}"/);
    });

    it('lists the snippets it may include, a snippet that would loop disabled', async () => {
        const view = drawEditor(PRODUCT);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'includeCandidates')));
        fireEvent.click(view.container.querySelector('#rs-editor-row-1') ?? assert.fail('the brand row'));
        const loops = within(drawer()).getByLabelText(/^Product/);
        assert.equal(loops.hasAttribute('disabled'), true);
        assert.ok((drawer().textContent ?? '').includes('editor.include.loops'));
    });

    it('sets the field a property is output only when', () => {
        const view = drawEditor(PRODUCT);
        fireEvent.click(view.container.querySelector('#rs-editor-row-0') ?? assert.fail('the name row'));
        fireEvent.click(drawer().querySelector('#rs-editor-when-switch') ?? assert.fail('the condition switch'));
        fireEvent.click(button(drawer(), 'editor.drawer.done'));
        assert.match(view.store.state().draft.template, /"name": \{\n {4}"@if": "product\.on_sale",\n {4}"@value": "\{\{product\.name\}\}"/);
    });

    it('marks rows empty on the preview page and says what a property gives there', async () => {
        const view = drawEditor(PRODUCT);
        const sample = { ref: 'product:12', kind: 'product', id: 12, label: 'Field satchel', url: 'https://shop.test/a', path: '/a' };
        view.store.showPreview({ sample, state: 'output', output: { name: 'Field satchel', offers: {} }, message: null, checks: [] });
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('editor.row.empty')));
        assert.ok((view.container.textContent ?? '').includes('editor.properties.legend Field satchel'));
        fireEvent.click(view.container.querySelector('#rs-editor-row-0') ?? assert.fail('the name row'));
        assert.ok((drawer().textContent ?? '').includes('"Field satchel"'));
    });
});

describe('the Pages tab', () => {
    it('mounts the expression builder with its configuration and hands its changes to the draft', async () => {
        const view = drawEditor(PRODUCT);
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.equal(view.ports.builder.mounts.length, 1));
        assert.equal(view.ports.builder.mounts[0]?.config['combineType'], 'rich_snippets/combine');
        assert.ok(view.container.querySelector('[data-role="rs-editor-conditions"][data-mounted="true"]'));
        view.ports.builder.change('');
        assert.equal(view.store.state().draft.conditions, '');
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('Output on product pages where')));
    });

    it('prunes the conditions of a kind unpicked, takes the builder away, and keeps every store view exclusive', async () => {
        const view = drawEditor(PRODUCT);
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.equal(view.ports.builder.mounts.length, 1));
        assert.match(view.store.state().draft.conditions, /product_type/);
        fireEvent.click(button(view.container, 'Product pages'));
        assert.deepEqual(view.store.state().draft.pageKinds, []);
        assert.equal(view.store.state().draft.conditions, '');
        await waitFor(() => assert.equal(view.ports.builder.destroyed, 1));
        assert.ok((view.container.textContent ?? '').includes('editor.pages.noKind'));
        fireEvent.click(button(view.container, 'Deutsch'));
        assert.deepEqual(view.store.state().draft.storeIds, [2]);
        fireEvent.click(button(view.container, 'editor.pages.allStores'));
        assert.deepEqual(view.store.state().draft.storeIds, [0]);
    });

    it('asks an admin who may only see the module for no sample page, no catalog values and no count', async () => {
        const view = drawEditor(PRODUCT, { canChange: false, canTest: false });
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.equal(view.ports.builder.mounts.length, 1));

        assert.deepEqual(view.ports.calls.find((call) => call.method === 'fields')?.args, [null, null]);
        assert.deepEqual(
            view.ports.calls.filter((call) => call.method === 'samples' || call.method === 'placementCount'),
            [],
        );
        assert.ok(!(view.container.textContent ?? '').includes('Output on product pages'));
    });

    it('shows a condition tree changed from outside in the builder without remounting it', async () => {
        const view = drawEditor(PRODUCT);
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.equal(view.ports.builder.mounts.length, 1));
        view.store.change((draft) => ({ ...draft, conditions: '' }));
        await waitFor(() => assert.deepEqual(view.ports.builder.shown, ['']));
        assert.equal(view.ports.builder.mounts.length, 1);
    });
});

describe('a building block and a blank snippet', () => {
    it('draws a building block without tabs or Pages, with the snippets that use it linked', () => {
        const view = drawEditor(blockData());
        assert.equal(view.container.querySelectorAll('[role="tab"]').length, 0);
        assert.ok((view.container.textContent ?? '').includes('editor.snippet.block'));
        const links = [...view.container.querySelectorAll<HTMLAnchorElement>('.rs-editor__used a')];
        assert.deepEqual(links.map((link) => link.textContent), ['Organization', 'Product', 'Product with variants']);
        assert.match(links[1]?.href ?? '', /\/edit\/key\/e1\/\?id=3$/);
    });

    it('suggests types for a blank snippet and starts the template from the one picked', () => {
        const view = drawEditor(blankData());
        assert.ok((view.container.textContent ?? '').includes('editor.title.new'));
        assert.ok((view.container.textContent ?? '').includes('editor.properties.start.title'));
        fireEvent.click(button(view.container, 'OnlineStore'));
        assert.match(view.store.state().draft.template, /"@type": "OnlineStore"/);
        assert.equal(view.store.state().draft.type, 'OnlineStore');
        assert.ok((view.container.textContent ?? '').includes('editor.properties.empty.title'));
    });
});
