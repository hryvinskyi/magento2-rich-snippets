/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library's rules on the contract's libraries: what a search finds and how the chips count it, how the templates
 * fall into sections, when the recommended set is offered, what the drawer warns about and what its button says,
 * which answer a question stands at and which answers an add sends, and what the message after an add says and links.
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { parseAddAnswer, parseLibrary, parseTemplateAnswers } from '../adapters/library-data.ts';
import {
    contractAdd,
    contractLibrary,
    contractSuggestions,
    libraryChanging,
    type AddCase,
    type LibraryCase,
} from '../../test/library-fixtures.ts';
import {
    addButtonOf,
    addedMessageOf,
    againNoticeOf,
    answersToSend,
    chosenOptionOf,
    EDITOR,
    recommendedSetOf,
    refusalOf,
    setMessageOf,
    TEST,
    useLineOf,
} from './add-view.ts';
import { chipKey, chipsOf, matchesSearch, searched, sectionsOf } from './filters.ts';
import type { Library, LibraryTemplate } from './model.ts';

const library = (which: LibraryCase = 'lib'): Library => parseLibrary(contractLibrary(which), '$');

const templateOf = (code: string, from: Library = library()): LibraryTemplate => {
    const found = from.templates.find((template) => template.code === code);
    assert.ok(found !== undefined, `the library holds ${code}`);
    return found;
};

const addAnswer = (which: AddCase) => parseAddAnswer(contractAdd(which), '$');

describe('the search and the chips', () => {
    it('finds a template by its name, type, description or Google line, whatever the case and the spaces around it', () => {
        const product = templateOf('product');

        assert.ok(matchesSearch(product, '  PRODUCT '));
        assert.ok(matchesSearch(templateOf('organization'), 'onlinestore'), 'by its type');
        assert.ok(matchesSearch(templateOf('local-business'), 'opening hours'), 'by its description');
        assert.ok(matchesSearch(templateOf('faq'), 'since 2026'), 'by its Google line');
        assert.ok(matchesSearch(product, ''), 'an empty search finds every template');
        assert.equal(matchesSearch(product, 'opening hours'), false);
    });

    it('counts each chip over what the search found, every group first', () => {
        const chips = chipsOf(library(), 'product');

        assert.deepEqual(chips.map((chip) => [chip.key, chip.count]), [
            ['all', 4],
            ['group:shop', 0],
            ['group:product', 2],
            ['group:category', 1],
            ['group:content', 0],
            ['group:blocks', 1],
        ]);
        assert.deepEqual(chips[0]?.label, { key: 'library.chips.all', params: [] });
        assert.equal(chips[1]?.label, 'The shop', 'a group chip carries the server\'s label');
        assert.equal(chipKey(null), 'all');
        assert.equal(chipKey('blocks'), 'group:blocks');
    });

    it('heads each group with its label while every group is shown, and drops a group the search left empty', () => {
        const all = sectionsOf(library(), 'product', null);
        const blocks = sectionsOf(library(), '', 'blocks');

        assert.deepEqual(all.map((section) => [section.group.code, section.heading, section.templates.length]), [
            ['product', true, 2],
            ['category', true, 1],
            ['blocks', true, 1],
        ]);
        assert.deepEqual(blocks.map((section) => [section.group.code, section.heading]), [['blocks', false]]);
        assert.deepEqual(blocks[0]?.templates.map((template) => template.code), ['return-policy', 'shipping-policy', 'postal-address']);
        assert.deepEqual(sectionsOf(library(), 'nothing like it', null), []);
        assert.equal(searched(library(), '').length, 13);
    });
});

describe('the recommended set', () => {
    it('is offered in the server\'s order while the shop lacks one of its templates', () => {
        assert.deepEqual(recommendedSetOf(library('first')).map((template) => template.code), [
            'organization',
            'website-name',
            'breadcrumbs',
            'product',
            'product-variants',
        ]);
        const oneMissing = parseLibrary(libraryChanging('breadcrumbs', { added: false }), '$');
        assert.equal(recommendedSetOf(oneMissing).length, 5);
    });

    it('is not offered once the shop has every template of it, nor for a code the library does not hold', () => {
        assert.deepEqual(recommendedSetOf(library('lib')), []);
        assert.deepEqual(recommendedSetOf({ ...library('first'), recommended: ['gone'] }), []);
        assert.deepEqual(
            recommendedSetOf({ ...library('first'), recommended: ['gone', 'faq'] }).map((template) => template.code),
            ['faq'],
        );
    });
});

describe('the add drawer', () => {
    it('warns that the shop has a template already, naming the copy it would add; the product in its own words', () => {
        assert.equal(againNoticeOf(templateOf('faq', library('first'))), null);
        assert.deepEqual(againNoticeOf(templateOf('faq')), { key: 'library.again.other', params: ['FAQ', 'FAQ 2'] });
        assert.deepEqual(againNoticeOf(templateOf('product')), { key: 'library.again.product', params: [] });
        assert.deepEqual(addButtonOf(templateOf('faq')), { key: 'library.drawer.add', params: [] });
    });

    it('offers a newer version beside the copy the shop has', () => {
        const newer = templateOf('faq', parseLibrary(libraryChanging('faq', { hasNewerVersion: true }), '$'));

        assert.deepEqual(againNoticeOf(newer), { key: 'library.drawer.newer', params: ['FAQ 2'] });
        assert.deepEqual(addButtonOf(newer), { key: 'library.drawer.addNewer', params: [] });
    });

    it('says of each building block whether the shop has it already', () => {
        const [returns, address] = templateOf('organization', library('first')).uses;

        assert.deepEqual(useLineOf({ code: 'return-policy', name: 'Return policy', inShop: true }), { key: 'library.blocks.have', params: [] });
        assert.ok(returns !== undefined && address !== undefined);
        assert.deepEqual(useLineOf(returns), { key: 'library.blocks.new', params: [] });
    });

    it('stands each question at the answer chosen, else the suggested one, and sends one answer per question', () => {
        const [brand, gtin] = parseTemplateAnswers(contractSuggestions('product'), '$').questions;
        assert.ok(brand !== undefined && gtin !== undefined);

        assert.equal(chosenOptionOf(brand, new Map())?.key, 'attribute:manufacturer');
        assert.equal(chosenOptionOf(brand, new Map([['brand', 'leave_out']]))?.tone, 'warn');
        assert.equal(chosenOptionOf(brand, new Map([['brand', 'gone']]))?.key, 'attribute:manufacturer', 'an answer no longer offered');
        assert.equal(chosenOptionOf({ ...brand, options: [] }, new Map()), null);

        const questions = parseTemplateAnswers(contractSuggestions('product'), '$').questions;
        assert.deepEqual(Object.fromEntries(answersToSend(questions, new Map([['gtin', 'leave_out']]))), {
            brand: 'attribute:manufacturer',
            gtin: 'leave_out',
            description: 'attribute:short_description',
        });
    });
});

describe('the message after an add', () => {
    it('names the copy added and links to its editor, passing over the block it reused', () => {
        const again = addedMessageOf(addAnswer('again'), templateOf('product'));

        assert.deepEqual(again.sentence, { phrase: { key: 'library.toast.added', params: ['Product 2'] }, count: null });
        assert.equal(again.link?.to.route, EDITOR);
        assert.deepEqual(Object.fromEntries(again.link?.to.params ?? []), { id: '43' });
        assert.deepEqual(again.link?.label, { key: 'library.toast.open', params: [] });
    });

    it('says a building block was added in words of its own', () => {
        const block = addedMessageOf(addAnswer('block'), templateOf('shipping-policy', library('first')));

        assert.deepEqual(block.sentence.phrase, { key: 'library.toast.addedBlock', params: ['Shipping policy'] });
        assert.deepEqual(Object.fromEntries(block.link?.to.params ?? []), { id: '44' });
    });

    it('names the copy it expected and links nowhere when the answer holds no new copy of the template', () => {
        const none = addedMessageOf({ ...addAnswer('again'), added: [] }, templateOf('faq'));

        assert.deepEqual(none.sentence.phrase, { key: 'library.toast.added', params: ['FAQ 2'] });
        assert.equal(none.link, null);
    });

    it('counts the copies and the building blocks of the recommended set, and links to the Test page', () => {
        const set = setMessageOf(addAnswer('recommended'));

        assert.deepEqual(set.sentence, { phrase: { key: 'library.toast.setBlocks', params: ['5'] }, count: 2 });
        assert.equal(set.link?.to.route, TEST);
        assert.equal(set.link?.to.params.size, 0);
        assert.deepEqual(set.link?.label, { key: 'library.toast.openTest', params: [] });
    });

    it('counts only the copies when the set added no building block, and says so when it added nothing', () => {
        const answer = addAnswer('recommended');
        const copiesOnly = setMessageOf({ ...answer, added: answer.added.filter((snippet) => !snippet.isBlock) });
        const nothing = setMessageOf({ ...answer, added: answer.added.map((snippet) => ({ ...snippet, reused: true })) });

        assert.deepEqual(copiesOnly.sentence, { phrase: { key: 'library.toast.set', params: [] }, count: 5 });
        assert.deepEqual(nothing, { sentence: { phrase: { key: 'library.toast.setNone', params: [] }, count: null }, link: null });
    });

    it('words a refusal the page knows by its reason, and every other in general words', () => {
        assert.deepEqual(refusalOf('template_invalid'), { key: 'library.refused.template_invalid', params: [] });
        assert.deepEqual(refusalOf('include_loop'), { key: 'library.refused.other', params: [] });
    });
});
