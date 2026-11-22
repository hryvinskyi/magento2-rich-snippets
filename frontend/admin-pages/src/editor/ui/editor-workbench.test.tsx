/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The whole snippet editor drawn from the document the server prints, with fake ports that hold the previews and the
 * saves until the test answers them: the Code tab valid, being typed, wrong, and offering fields; the Preview panel
 * with its sample picker, its output and Copy; previews on rapid edits; the Checks panel putting a property back and
 * opening the Pages section; Save refused for a missing type, failed with Try again, answered by someone else's save
 * with Reload, and saved; Test on the previewed page; and everything read in the store view the snippet is output in.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, waitFor } from '@testing-library/preact';
import { render } from 'preact';
import { Icon, RequestError } from '@hryvinskyi/admin-ui';
import { blankData, blockData, editorData } from '../../test/editor-fixtures.ts';
import { contractPreview, contractSave, drawWorkbench, type DrawnEditor } from '../../test/editor-harness.tsx';

afterEach(cleanup);

const button = (root: Element, text: string): HTMLButtonElement => {
    const found = [...root.querySelectorAll<HTMLButtonElement>('button')].find((item) => (item.textContent ?? '').includes(text));
    assert.ok(found !== undefined, `a button "${text}"`);
    return found;
};

const TEMPLATE = '{\n  "@context": "https://schema.org",\n  "@type": "Product",\n  "name": "{{product.name}}",\n  "sku": "{{product.sku}}",\n  "aggregateRating": {\n    "@type": "AggregateRating"\n  }\n}';
const PRODUCT = editorData({ template: TEMPLATE });

const codeArea = (view: DrawnEditor): HTMLTextAreaElement =>
    view.container.querySelector<HTMLTextAreaElement>('#rs-editor-code') ?? assert.fail('the code area');

const typeCode = (view: DrawnEditor, text: string): void => {
    fireEvent.input(codeArea(view), { target: { value: text } });
};

const status = (view: DrawnEditor): string => view.container.querySelector('.hui-code-editor__message')?.textContent ?? '';

/** Draws the editor and answers its first preview with the contract's output. */
async function drawShown(data: unknown = PRODUCT): Promise<DrawnEditor> {
    const view = drawWorkbench(data);
    await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
    const first = view.ports.drafts.previews[0];
    first?.answer(contractPreview('output')(first.args.sample));
    await waitFor(() => assert.ok(view.container.querySelector('.rs-editor__output') !== null));
    return view;
}

const openCode = (view: DrawnEditor): void => {
    fireEvent.click(button(view.container, 'editor.editAs.code'));
};

describe('the Builder | Code choice', () => {
    it('draws each option with its glyph before its name', () => {
        const view = drawWorkbench(PRODUCT);
        const glyph = (name: string): string | null => {
            const host = document.createElement('div');
            render(<Icon name={name} />, host);
            return host.querySelector('path')?.getAttribute('d') ?? null;
        };
        const drawn = (option: string): string | null | undefined =>
            button(view.container, option).querySelector('svg[aria-hidden="true"] path')?.getAttribute('d');

        assert.notEqual(glyph('account_tree'), null);
        assert.equal(drawn('editor.editAs.builder'), glyph('account_tree'));
        assert.equal(drawn('editor.editAs.code'), glyph('code'));
    });
});

describe('the Code tab', () => {
    it('shows the template valid, with its lines and fields, and the hint', async () => {
        const view = await drawShown();
        openCode(view);
        assert.equal(codeArea(view).value, TEMPLATE);
        assert.equal(codeArea(view).getAttribute('aria-label'), 'editor.code.area %1 %2 %3');
        assert.match(status(view), /^editor\.code\.valid editor\.code\.lines\.other 9 .* editor\.code\.fields\.other 2 /);
        assert.ok((view.container.textContent ?? '').includes('editor.code.hint'));
        assert.ok((view.container.textContent ?? '').includes('editor.properties.subtitleCode'));
    });

    it('says a line being typed is not finished, keeps the last valid template and turns Save and the builder off', async () => {
        const view = await drawShown();
        openCode(view);
        typeCode(view, TEMPLATE.replace('"sku": "{{product.sku}}",', '"sku": "{{product.s'));
        assert.match(status(view), /^editor\.code\.unfinished 5 /);
        assert.equal(view.store.state().draft.template, TEMPLATE);
        assert.equal(view.store.state().codeInvalid, true);
        assert.equal(button(view.container, 'editor.header.save').disabled, true);
        assert.equal(button(view.container, 'editor.editAs.builder').disabled, true);
        assert.ok((view.container.textContent ?? '').includes('editor.preview.noteLastValid'));
    });

    it('marks the line a comma is missing on and says so in words', async () => {
        const view = await drawShown();
        openCode(view);
        typeCode(view, TEMPLATE.replace('"name": "{{product.name}}",', '"name": "{{product.name}}"'));
        assert.match(status(view), /^editor\.code\.missingComma 5 4 /);
        assert.equal(view.container.querySelector('.hui-code-editor__message')?.getAttribute('role'), 'alert');
        const marked = view.container.querySelector('.hui-code-editor__number[data-marked="true"]');
        assert.equal(marked?.textContent, '5');
    });

    it('puts valid code into the draft, type and all, and lets the builder show it again', async () => {
        const view = await drawShown();
        openCode(view);
        const offer = TEMPLATE.replace('"@type": "Product"', '"@type": "Offer"');
        typeCode(view, offer);
        assert.equal(view.store.state().draft.template, offer);
        assert.equal(view.store.state().draft.type, 'Offer');
        fireEvent.click(button(view.container, 'editor.editAs.builder'));
        assert.ok(view.container.querySelector('#rs-editor-code') === null);
        assert.ok(view.container.querySelectorAll('.rs-editor__row').length > 0);
    });

    it('offers the catalog\'s fields after two braces, narrowing as the path is typed', async () => {
        const view = await drawShown();
        openCode(view);
        const at = TEMPLATE.indexOf('{{product.sku}}');
        const typing = `${TEMPLATE.slice(0, at)}{{product.m`;
        fireEvent.input(codeArea(view), { target: { value: typing, selectionStart: typing.length, selectionEnd: typing.length } });
        const list = await waitFor(() => view.container.querySelector('[role="listbox"]') ?? assert.fail('the field list'));
        assert.equal(list.getAttribute('aria-label'), 'editor.code.listLabel %1 %2 %3');
        assert.deepEqual([...list.querySelectorAll('[role="option"]')].map((option) => option.textContent?.startsWith('product.manufacturer')), [true]);
    });
});

describe('the Preview panel', () => {
    it('is not drawn, and no preview is asked for, for an admin who may only see the module', async () => {
        const view = drawWorkbench(PRODUCT, { canChange: false, canTest: false });
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        view.ports.scheduler.advance(5000);

        assert.equal(view.ports.drafts.previews.length, 0);
        assert.equal(view.container.querySelector('.rs-editor__aside'), null);
        assert.equal(view.container.querySelector('.rs-editor__preview'), null);
        assert.ok(view.container.querySelector('.rs-editor__row') !== null, 'the properties stay readable');
    });

    it('previews the first sample in the default store view, shows the output and copies it', async () => {
        const view = await drawShown();
        const asked = view.ports.drafts.previews[0]?.args;
        assert.equal(asked?.sample.ref, 'product:12');
        assert.equal(asked?.store, null);
        const panel = view.container.querySelector('.rs-editor__preview') ?? assert.fail('the preview panel');
        assert.ok((panel.textContent ?? '').includes('/bags/card-sleeve.html'));
        assert.ok((panel.textContent ?? '').includes('Product page'));
        assert.ok((panel.textContent ?? '').includes('"name": "Card sleeve"'));
        assert.ok((panel.textContent ?? '').includes('editor.preview.notePage'));
        fireEvent.click(button(panel, 'editor.preview.copy'));
        await waitFor(() => assert.ok((panel.textContent ?? '').includes('editor.preview.copied')));
        assert.match(view.ports.clipboard.written[0] ?? '', /^\{\n {2}"@context": "https:\/\/schema\.org",/);
        view.ports.scheduler.advance(1600);
        await waitFor(() => assert.ok((panel.textContent ?? '').includes('editor.preview.copy %1')));
    });

    it('previews another sample at once when one is picked, and reads the catalog for it', async () => {
        const view = await drawShown();
        const picker = view.container.querySelector<HTMLSelectElement>('.rs-editor__sample select') ?? assert.fail('the sample picker');
        assert.equal(picker.getAttribute('aria-label'), 'editor.preview.sample %1 %2 %3');
        fireEvent.change(picker, { target: { value: 'home:2' } });
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 2));
        const second = view.ports.drafts.previews[1];
        assert.equal(second?.args.sample.ref, 'home:2');
        second?.answer(contractPreview('noType', { sample: null })(second.args.sample));
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('Nothing to show yet')));
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields' && call.args[0] === 'home:2')));
    });

    it('sends one preview for rapid edits, aborting the one on its way, and shows only the latest answer', async () => {
        const view = await drawShown();
        view.store.change((draft) => ({ ...draft, template: draft.template.replace('product.sku', 'product.mpn') }));
        view.ports.scheduler.advance(100);
        view.store.change((draft) => ({ ...draft, template: draft.template.replace('product.mpn', 'product.ean') }));
        await waitFor(() => assert.equal(view.ports.scheduler.pending(), 1));
        view.ports.scheduler.advance(400);
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 2));
        assert.match(view.ports.drafts.previews[1]?.args.draft.template ?? '', /product\.ean/);
        view.store.change((draft) => ({ ...draft, template: draft.template.replace('product.ean', 'product.gtin') }));
        await waitFor(() => assert.equal(view.ports.drafts.previews[1]?.signal.aborted, true));
        view.store.change((draft) => ({ ...draft, name: 'Only the name' }));
        view.ports.scheduler.advance(400);
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 3));
        assert.equal(view.ports.scheduler.pending(), 0, 'a new name asks for no preview');
    });

    it('says the preview failed, and tries again', async () => {
        const view = drawWorkbench(PRODUCT);
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
        view.ports.drafts.previews[0]?.fail(new RequestError('timeout'));
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('editor.preview.failed')));
        fireEvent.click(button(view.container, 'editor.preview.retry'));
        assert.equal(view.ports.drafts.previews.length, 2);
    });

    it('reads everything in the first store view the snippet is output in', async () => {
        const view = drawWorkbench(editorData({ template: TEMPLATE, storeIds: [2] }));
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
        assert.equal(view.ports.drafts.previews[0]?.args.store, 2);
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'samples')?.args, [['product'], view.store.state().draft.conditions, 2]);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'fields')));
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'fields')?.args, ['product:12', 2]);
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'placementCount')));
        assert.equal(view.ports.calls.find((call) => call.method === 'placementCount')?.args[1], 2);
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'builderConfig')));
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'builderConfig')?.args, [['product'], 2]);
    });

    it('asks for the sample pages the snippet\'s conditions reach first, again when the conditions change', async () => {
        const simple = JSON.stringify({ type: 'rich_snippets/combine', aggregator: 'all', conditions: [
            { type: 'product_type', attribute: null, operator: '==', value: 'simple', is_value_processed: null },
        ] });
        const view = drawWorkbench(editorData({ template: TEMPLATE, conditions: simple }));
        await waitFor(() => assert.ok(view.ports.calls.some((call) => call.method === 'samples')));
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'samples')?.args, [['product'], simple, null]);

        view.store.change((draft) => ({ ...draft, conditions: '' }));

        await waitFor(() => assert.equal(view.ports.calls.filter((call) => call.method === 'samples').length, 2));
        assert.deepEqual(view.ports.calls.filter((call) => call.method === 'samples')[1]?.args, [['product'], '', null]);
    });

    it('previews a building block on a page of any kind, as inside the snippet that uses it', async () => {
        const view = drawWorkbench(blockData());
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
        assert.deepEqual(view.ports.calls.find((call) => call.method === 'samples')?.args, [[], '', null]);
        const first = view.ports.drafts.previews[0];
        first?.answer(contractPreview('output')(first.args.sample));
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('editor.preview.noteBlock')));
        assert.ok((view.container.textContent ?? '').includes('editor.preview.metaBlock Organization Product page'));
    });
});

describe('the Checks panel', () => {
    it('counts the requirements missing and puts a property back from the value the check carries', async () => {
        const view = drawWorkbench(PRODUCT);
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
        const first = view.ports.drafts.previews[0];
        first?.answer(contractPreview('output', {
            checks: [{
                tone: 'bad',
                title: 'Google needs offers for a merchant listing',
                text: 'Required: name, image and an offer with a price and a currency.',
                action: { label: 'Add offers', path: 'offers', icon: 'add', value: '{"@type": "Offer", "price": "{{product.final_price}}"}', before: ['aggregateRating'] },
            }],
        })(first.args.sample));
        const checks = await waitFor(() => view.container.querySelector('.rs-editor__checks') ?? assert.fail('the checks'));
        await waitFor(() => assert.ok((checks.textContent ?? '').includes('Google needs offers')));
        assert.equal(checks.querySelector('.rs-editor__check-count')?.textContent, '1');
        assert.equal(checks.querySelector('.rs-editor__check-count')?.getAttribute('data-tone'), 'bad');
        fireEvent.click(button(checks, 'Add offers'));
        assert.match(view.store.state().draft.template, /"sku": "\{\{product\.sku\}\}",\n {2}"offers": \{\n {4}"@type": "Offer",\n {4}"price": "\{\{product\.final_price\}\}"\n {2}\},\n {2}"aggregateRating"/);
        assert.ok((view.container.textContent ?? '').includes('editor.header.unsaved'));
    });

    it('opens the Pages section from the row of a page that does not meet the conditions', async () => {
        const view = drawWorkbench(PRODUCT);
        await waitFor(() => assert.equal(view.ports.drafts.previews.length, 1));
        const first = view.ports.drafts.previews[0];
        first?.answer(contractPreview('conditionsNotMet')(first.args.sample));
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('This page does not meet the conditions')));
        fireEvent.click(button(view.container, 'See the conditions'));
        await waitFor(() => assert.equal(view.container.querySelector('[role="tab"][aria-selected="true"]')?.id, 'rs-editor-tab-pages'));
    });
});

describe('the save', () => {
    it('does not save a snippet without a type, and marks the type', async () => {
        const view = drawWorkbench(blankData());
        fireEvent.click(button(view.container, 'editor.header.save'));
        assert.equal(view.ports.drafts.saves.length, 0);
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('editor.snippet.typeError')));
    });

    it('says a save the server did not answer failed, keeps the changes, and saves again with the same key', async () => {
        const view = drawWorkbench(blankData());
        fireEvent.input(view.container.querySelector('#rs-editor-type') ?? assert.fail('the type field'), { target: { value: 'Product' } });
        fireEvent.click(button(view.container, 'editor.header.save'));
        assert.equal(button(view.container, 'editor.header.save').getAttribute('aria-busy'), 'true');
        view.ports.drafts.saves[0]?.fail(new RequestError('network'));
        const strip = await waitFor(() => view.container.querySelector('[role="alert"]') ?? assert.fail('the error strip'));
        await waitFor(() => assert.ok((strip.textContent ?? '').includes('editor.save.failed')));
        assert.equal(view.store.state().draft.type, 'Product');
        fireEvent.click(button(view.container, 'editor.save.retry'));
        assert.equal(view.ports.drafts.saves.length, 2);
        assert.equal(view.ports.drafts.saves[1]?.args.key, 'key-1');
        assert.equal(view.ports.drafts.saves[0]?.args.key, 'key-1');
    });

    it('says someone else saved first, with the server\'s sentence and Reload', async () => {
        const view = drawWorkbench(PRODUCT);
        view.store.change((draft) => ({ ...draft, name: 'Bag' }));
        fireEvent.click(button(view.container, 'editor.header.save'));
        assert.equal(view.ports.drafts.saves[0]?.args.updatedAt, '2026-11-12 09:30:00');
        view.ports.drafts.saves[0]?.answer(contractSave('conflict'));
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('Someone else saved this snippet at')));
        fireEvent.click(button(view.container, 'editor.save.reload'));
        assert.deepEqual(view.ports.navigation.visits, ['reload']);
        assert.equal(view.store.state().draft.name, 'Bag');
    });

    it('says the snippet was saved and where the new version is seen, and takes the message away on the next change', async () => {
        const view = drawWorkbench(PRODUCT);
        view.store.change((draft) => ({ ...draft, name: 'Bag' }));
        fireEvent.click(button(view.container, 'editor.header.save'));
        view.ports.drafts.saves[0]?.answer({ kind: 'saved', draft: { ...view.store.state().draft, updatedAt: '2026-11-22 10:00:00' } });
        await waitFor(() => assert.ok((view.container.textContent ?? '').includes('editor.save.saved Bag')));
        assert.ok((view.container.textContent ?? '').includes('editor.save.live'));
        assert.ok(!(view.container.textContent ?? '').includes('editor.header.unsaved'));
        view.store.change((draft) => ({ ...draft, name: 'Bag 2' }));
        await waitFor(() => assert.ok(!(view.container.textContent ?? '').includes('editor.save.saved')));
    });

    it('shows a snippet it created at its own editor\'s address', async () => {
        const view = drawWorkbench(blankData());
        view.store.change((draft) => ({ ...draft, type: 'Product' }));
        fireEvent.click(button(view.container, 'editor.header.save'));
        view.ports.drafts.saves[0]?.answer({ kind: 'saved', draft: { ...view.store.state().draft, id: 11, code: 'product-2' } });
        await waitFor(() => assert.equal(view.ports.navigation.visits.length, 1));
        assert.match(view.ports.navigation.visits[0] ?? '', /^replace https:\/\/admin\.test\/admin\/richsnippets\/snippets\/edit\/key\/e1\/\?id=11$/);
    });
});

describe('the header', () => {
    it('opens the page test on the previewed page', async () => {
        const view = await drawShown();
        fireEvent.click(button(view.container, 'editor.header.test'));
        assert.match(view.ports.navigation.visits[0] ?? '', /^go https:\/\/admin\.test\/admin\/richsnippets\/test\/index\/key\/t1\/\?page=product%3A12$/);
    });
});

describe('the Pages tab', () => {
    it('says "Only when" once: the builder\'s own heading, and the card\'s only where no builder is drawn', async () => {
        const view = drawWorkbench(PRODUCT);
        fireEvent.click(button(view.container, 'editor.tabs.pages'));
        await waitFor(() => assert.equal(view.ports.builder.mounts.length, 1));
        assert.equal(view.ports.builder.mounts[0]?.label, 'editor.pages.onlyWhen %1 %2 %3');
        assert.ok(view.container.querySelector('.rs-editor__pages-heading .rs-editor__tag') === null);
        fireEvent.click(button(view.container, 'Product pages'));
        await waitFor(() => assert.ok(view.container.querySelector('.rs-editor__pages-heading .rs-editor__tag') !== null));
    });

    it('opens on the Pages tab when the address asks for it', () => {
        const view = drawWorkbench(editorData({ template: TEMPLATE }, { section: 'pages' }));
        assert.equal(view.container.querySelector('[role="tab"][aria-selected="true"]')?.id, 'rs-editor-tab-pages');
    });
});
