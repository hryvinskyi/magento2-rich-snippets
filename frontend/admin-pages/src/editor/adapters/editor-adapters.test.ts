/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The editor's readers against the server's contract files, its endpoints over a recording JSON client, and the
 * expression builder's loader over a fake module loader.
 */
import assert from 'node:assert/strict';
import { it } from 'node:test';
import { isContractError, type JsonClient, type JsonRequestOptions } from '@hryvinskyi/admin-ui';
import { blockData, catalogAnswer, editorData, previewAnswer, saveAnswer } from '../../test/editor-fixtures.ts';
import { amdConditionsWidgets, BUILDER_MODULE, BuilderLoadError, type ModuleLoader } from './conditions-loader.ts';
import { parseBuilderConfig, parseCandidates, parseEditorData, parseFieldCatalog, parsePlacementCount, parseSamples } from './editor-data.ts';
import { httpEditorEndpoints } from './editor-endpoints.ts';
import { draftBody, httpDraftGateway, parsePreview, parseSaveAnswer } from './draft-endpoints.ts';

it('reads the first data and every catalog answer of the contract', () => {
    const data = parseEditorData(editorData(), '$');
    assert.equal(data.snippet.code, 'product');
    assert.deepEqual(data.snippet.origin, { code: 'product', version: 3 });
    assert.deepEqual(data.conditions.get('product')?.map((field) => field.code), ['product_attribute', 'attribute_set', 'product_category', 'product_type']);
    assert.deepEqual(data.conditions.get('home'), []);
    assert.equal(data.defaults.list.item, 'image.url');
    assert.equal(data.section, 'properties');
    assert.equal(data.kinds[1]?.page, 'Product page');
    assert.deepEqual(parseEditorData(blockData(), '$').usedIn.map((includer) => includer.id), [5, 3, 4]);
    const catalog = parseFieldCatalog(catalogAnswer('fields'), '$');
    assert.equal(catalog.groups[0]?.fields[0]?.example, 'Field satchel');
    assert.equal(catalog.suggestions.get('OnlineStore')?.get('hasMerchantReturnPolicy')?.[0]?.required, true);
    assert.equal(catalog.sample?.ref, 'product:12');
    assert.equal(parseSamples(catalogAnswer('samples'), '$').length, 2);
    assert.equal(parseCandidates(catalogAnswer('includeCandidates'), '$')[1]?.loops, true);
    assert.equal(parseBuilderConfig(catalogAnswer('builderConfig'), '$')['combineType'], 'rich_snippets/combine');
    const count = parsePlacementCount(catalogAnswer('placementCount'), '$');
    assert.equal(count.kinds.get('product')?.matching, 146);
    assert.equal(count.asked, null);
});

it('names the path of the first wrong value', () => {
    assert.throws(() => parseEditorData(editorData({ storeIds: ['all'] }), '$'), (error: unknown) =>
        isContractError(error) && error.path === '$["snippet"]["storeIds"][0]');
});

it('asks each endpoint with its parameters and the read deadline, and posts the samples and the placement', async () => {
    const asked: { method: string; url: string; body?: unknown; options: JsonRequestOptions }[] = [];
    const client: JsonClient = {
        get: (url, options) => {
            asked.push({ method: 'GET', url, options });
            const name = url.split('/')[6] ?? '';
            return Promise.resolve(catalogAnswer(name === 'includeCandidates' || name === 'samples' || name === 'fields' || name === 'builderConfig' ? name : 'fields'));
        },
        post: (url, body, options) => {
            asked.push({ method: 'POST', url, body, options });
            return Promise.resolve(catalogAnswer(url.split('/')[6] === 'samples' ? 'samples' : 'placementCount'));
        },
    };
    const endpoints = httpEditorEndpoints(client, (name) => `https://admin.test/admin/richsnippets/snippets/${name}/`);
    const signal = new AbortController().signal;
    await endpoints.fields('product:12', null, signal);
    await endpoints.samples(['product', 'cms'], '{"type":"rich_snippets/combine"}', 1, signal);
    await endpoints.includeCandidates(null, signal);
    await endpoints.builderConfig(['product'], 2, signal);
    await endpoints.placementCount({ pageKinds: ['product'], conditions: '', storeIds: [0] }, null, signal);
    assert.deepEqual(asked.map((call) => [call.method, call.options.query ?? call.body]), [
        ['GET', { sample: 'product:12', store: '' }],
        ['POST', { kinds: ['product', 'cms'], conditions: '{"type":"rich_snippets/combine"}', store: 1 }],
        ['GET', { id: '' }],
        ['GET', { kinds: 'product', store: '2' }],
        ['POST', { pageKinds: ['product'], conditions: '', storeIds: [0], store: null }],
    ]);
    assert.ok(asked.every((call) => typeof call.options.timeoutMs === 'number'));
});

it('loads the expression builder once and mounts it with the rail, and fails on anything that is not one', async () => {
    const mounted: unknown[] = [];
    let loads = 0;
    const loader: ModuleLoader = (names, ready) => {
        loads++;
        assert.deepEqual(names, [BUILDER_MODULE]);
        ready({ mount: (_host: HTMLElement, options: unknown) => {
            mounted.push(options);
            return { setValue: () => undefined, destroy: () => undefined };
        } });
    };
    const widgets = amdConditionsWidgets(loader);
    const widget = await widgets.load();
    await widgets.load();
    widget.mount(document.createElement('div'), { value: '', config: { a: 1 }, label: 'Only when' }, () => undefined);
    assert.equal(loads, 1);
    assert.deepEqual(mounted, [{ value: '', builderConfig: { a: 1 }, label: 'Only when', joinerStyle: 'rail' }]);
    await assert.rejects(amdConditionsWidgets((_names, ready) => ready({})).load(), BuilderLoadError);
    await assert.rejects(amdConditionsWidgets((_names, _ready, failed) => failed(new Error('404'))).load(), BuilderLoadError);
});

const ASKED = {
    ref: 'product:99',
    kind: 'product',
    id: 99,
    label: 'Asked',
    url: 'https://linden.example/asked.html',
    path: '/asked.html',
};

it('reads each state of a preview the contract holds', () => {
    const output = parsePreview(previewAnswer('output'), '$', ASKED);
    assert.equal(output.state, 'output');
    assert.equal(output.sample.label, 'Card sleeve');
    assert.equal(output.output?.['name'], 'Card sleeve');
    assert.equal(output.message, null);
    assert.deepEqual(output.checks.map((row) => row.tone), ['ok', 'note', 'note', 'note']);
    const notMet = parsePreview(previewAnswer('conditionsNotMet'), '$', ASKED);
    assert.equal(notMet.output, null);
    assert.equal(notMet.message?.title, 'Not output for Weekender');
    assert.deepEqual(notMet.checks[0]?.action, { label: 'See the conditions', icon: 'arrow_forward', path: null, value: null, before: [] });
    assert.equal(parsePreview(previewAnswer('noType'), '$', ASKED).state, 'no_type');
    assert.equal(parsePreview({ ...previewAnswer('noType'), sample: null }, '$', ASKED).sample, ASKED);
});

it('reads a saved, a refused and a conflicting save the contract holds', () => {
    const saved = parseSaveAnswer(saveAnswer('saved'), '$');
    assert.equal(saved.kind === 'saved' ? saved.draft.updatedAt : null, '2026-11-12 09:30:00');
    const refused = parseSaveAnswer(saveAnswer('refused'), '$');
    assert.deepEqual(refused.kind === 'refused' ? refused.problems.map((problem) => problem.part) : null, ['type']);
    const conflict = parseSaveAnswer(saveAnswer('conflict'), '$');
    assert.match(conflict.kind === 'conflict' ? conflict.message : '', /^Someone else saved this snippet at /);
});

it('posts the draft for a preview with the read deadline and for a save that reads a 409 as its answer', async () => {
    const asked: { url: string; body: unknown; options: JsonRequestOptions }[] = [];
    const answers = [previewAnswer('output'), saveAnswer('conflict')];
    const client: JsonClient = {
        get: () => Promise.reject(new Error('no GET')),
        post: (url, body, options) => {
            asked.push({ url, body, options });
            return Promise.resolve(answers.shift());
        },
    };
    const gateway = httpDraftGateway(client, (name) => `https://admin.test/admin/richsnippets/snippets/${name}/`);
    const draft = parseEditorData(editorData(), '$').snippet;
    const signal = new AbortController().signal;
    await gateway.preview(draft, ASKED, 2, signal);
    const conflict = await gateway.save(draft, '2026-11-12 09:30:00', null, signal);
    assert.equal(conflict.kind, 'conflict');
    assert.deepEqual(asked.map((call) => [call.url.split('/')[6], call.body]), [
        ['preview', { snippet: draftBody(draft), sample: 'product:99', store: 2 }],
        ['save', { snippet: draftBody(draft), updatedAt: '2026-11-12 09:30:00', idempotencyKey: null }],
    ]);
    assert.equal(typeof asked[0]?.options.timeoutMs, 'number');
    assert.deepEqual(asked[1]?.options.answered, [409]);
    assert.deepEqual(Object.keys(draftBody(draft)), ['id', 'name', 'template', 'isBlock', 'active', 'pageKinds', 'conditions', 'storeIds']);
});

