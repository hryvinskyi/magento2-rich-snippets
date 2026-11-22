/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Library page drawn from the contract's libraries and driven the way a person drives it, found by role and name:
 * every template by group, the recommended set of a shop that has none, a search with the chips counting what it
 * found, a search that finds nothing, the add drawer with its questions, the already-added warnings, a refused and a
 * failed add, the messages after an add, the library loading and failed, and what an admin who may only look is left
 * with. The narrow page is a container query of the stylesheet, which a document without layout cannot apply; the
 * browser check covers it.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/preact';
import { RequestError } from '@hryvinskyi/admin-ui';
import { drawLibrary } from '../../test/library-harness.tsx';
import {
    contractAdd,
    contractLibrary,
    contractSuggestions,
    libraryChanging,
    libraryData,
    LIBRARY_ROUTES,
    unavailableData,
} from '../../test/library-fixtures.ts';

afterEach(cleanup);

const EDITOR = LIBRARY_ROUTES['hryvinskyi_richsnippets/snippets/edit'] ?? '';
const TEST = LIBRARY_ROUTES['hryvinskyi_richsnippets/test/index'] ?? '';
const NEW = LIBRARY_ROUTES['hryvinskyi_richsnippets/snippets/new'] ?? '';

const cardOf = (name: string): HTMLElement => screen.getByRole('article', { name });

const cardNames = (): readonly string[] =>
    [...document.querySelectorAll('.rs-library__template-name')].map((name) => name.textContent ?? '');

const chips = (): HTMLElement => screen.getByRole('group', { name: /library\.chips\.label/ });

const chip = (name: RegExp): HTMLElement => within(chips()).getByRole('button', { name });

const search = (): HTMLInputElement => {
    const input = screen.getByRole('searchbox', { name: /library\.search\.label/ });
    assert.ok(input instanceof HTMLInputElement);
    return input;
};

const drawer = (): HTMLElement => screen.getByRole('dialog');

const first = (): Record<string, unknown> => libraryData(contractLibrary('first'));

describe('b1 · the library', () => {
    it('draws the header action, the Templates card by group, and the side panels', () => {
        const { script } = drawLibrary(libraryData());

        assert.ok(screen.getByRole('heading', { level: 1, name: 'Library' }));
        assert.equal(screen.getByRole('link', { name: /library\.header\.blank/ }).getAttribute('href'), NEW);
        assert.ok(screen.getByText(/^library\.card\.subtitle\.other 13/));
        assert.deepEqual(
            [...document.querySelectorAll('.rs-library__group')].map((heading) => heading.textContent),
            ['The shop', 'Product pages', 'Category pages', 'Content pages', 'Building blocks'],
        );
        assert.equal(cardNames().length, 13);
        assert.ok(screen.getByRole('heading', { name: /library\.how\.title/ }));
        assert.equal(screen.getByRole('link', { name: /library\.notIn\.blank/ }).getAttribute('href'), NEW);
        assert.ok(screen.queryByRole('heading', { name: /library\.hero\.title/ }) === null, 'the shop has the recommended set');
        assert.deepEqual(script.errors, [], 'every phrase the page asked for is in its contract');
    });

    it('says on each card what it describes, where it goes and what Google does with it, and whether the shop has it', () => {
        drawLibrary(libraryData());
        const faq = cardOf('FAQ');
        const article = cardOf('Article');

        assert.ok(within(faq).getByText('FAQPage'));
        assert.ok(within(faq).getByText('CMS pages you choose'));
        assert.equal(within(faq).getByText('Google: no result since 2026').getAttribute('data-google'), 'gone');
        assert.ok(within(faq).getByText(/^library\.card\.added/));
        assert.ok(within(faq).getByRole('button', { name: /^library\.card\.addAgainNamed FAQ %2/ }));
        assert.ok(within(article).queryByText(/^library\.card\.added/) === null);
        assert.ok(within(article).getByRole('button', { name: /^library\.card\.addNamed Article %2/ }));
        assert.equal(screen.getAllByText(/^library\.card\.added/).length, 9);
    });
});

describe('b2 · a search and the group chips', () => {
    it('shows one group without its heading, and counts every chip', () => {
        drawLibrary(libraryData());

        fireEvent.click(chip(/^Building blocks/));

        assert.equal(chip(/^Building blocks/).getAttribute('aria-pressed'), 'true');
        assert.deepEqual(cardNames(), ['Return policy', 'Shipping policy', 'Postal address']);
        assert.equal(document.querySelectorAll('.rs-library__group').length, 0);
        assert.deepEqual(
            within(chips()).getAllByRole('button').map((button) => button.querySelector('.hui-chip__count')?.textContent),
            ['13', '4', '2', '2', '2', '3'],
        );
    });

    it('puts the chip back to every group when the search changes, the counts following what it found', () => {
        drawLibrary(libraryData());
        fireEvent.click(chip(/^Building blocks/));

        fireEvent.input(search(), { target: { value: 'product' } });

        assert.equal(chip(/^library\.chips\.all/).getAttribute('aria-pressed'), 'true');
        assert.deepEqual(cardNames(), ['Product', 'Product with variants', 'Product list', 'Return policy']);
        assert.deepEqual(
            within(chips()).getAllByRole('button').map((button) => button.querySelector('.hui-chip__count')?.textContent),
            ['4', '0', '2', '1', '0', '1'],
        );
    });
});

describe('b3 · nothing matches', () => {
    it('says so, starts a blank snippet, or clears the search', () => {
        drawLibrary(libraryData());

        fireEvent.input(search(), { target: { value: '  recipe ' } });

        assert.ok(screen.getByText(/^library\.noMatch\.title recipe/));
        assert.equal(screen.getByRole('link', { name: /library\.noMatch\.blank/ }).getAttribute('href'), NEW);
        fireEvent.click(screen.getByRole('button', { name: /library\.noMatch\.clear/ }));
        assert.equal(search().value, '');
        assert.equal(cardNames().length, 13);
    });
});

describe('b4 · the recommended set', () => {
    it('offers the set to a shop that lacks it, in its order, with what each adds', () => {
        drawLibrary(first());
        const hero = screen.getByRole('region', { name: /library\.hero\.title/ });

        assert.deepEqual(
            within(hero).getAllByRole('listitem').map((item) => item.querySelector('.rs-library__set-name')?.textContent),
            ['Organization', 'Website name', 'Breadcrumbs', 'Product', 'Product with variants'],
        );
        assert.ok(within(hero).getByText('Price, stock, brand, rating of a product'));
        assert.equal(screen.queryAllByText(/^library\.card\.added/).length, 0);
    });

    it('posts the set with its key, shows it on its way, then says what it added and links to the Test page', async () => {
        const { script } = drawLibrary(first());

        fireEvent.click(screen.getByRole('button', { name: /library\.hero\.add/ }));

        assert.deepEqual(script.callsTo('addRecommended'), [['key-1']]);
        assert.ok(screen.getByRole('button', { name: /library\.hero\.adding/ }));
        script.answer('addRecommended', contractAdd('recommended'));
        const toast = await waitFor(() => screen.getByRole('status'));
        assert.match(toast.textContent ?? '', /library\.toast\.setBlocks\.other 2 5/);
        assert.equal(within(toast).getByRole('link', { name: /library\.toast\.openTest/ }).getAttribute('href'), TEST);
        assert.ok(screen.queryByRole('region', { name: /library\.hero\.title/ }) === null, 'the shop has the set now');
    });

    it('says a failed set failed, and repeats the same request when it is pressed again', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /library\.hero\.add/ }));

        script.refuse('addRecommended', new RequestError('timeout'));
        await waitFor(() => assert.ok(screen.getByRole('alert')));
        fireEvent.click(screen.getByRole('button', { name: /library\.hero\.add/ }));

        assert.deepEqual(script.callsTo('addRecommended'), [['key-1'], ['key-1']]);
    });

    it('says when the set added nothing new', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /library\.hero\.add/ }));
        const answer = contractAdd('recommended');

        script.answer('addRecommended', { ...answer, added: [] });

        await waitFor(() => assert.match(screen.getByRole('status').textContent ?? '', /library\.toast\.setNone/));
        fireEvent.click(screen.getByRole('button', { name: /kit\.strip\.dismiss/ }));
        await waitFor(() => assert.ok(screen.queryByRole('status') === null));
    });
});

describe('b5 · the add drawer', () => {
    it('opens with what the template writes, where it goes, its blocks and Google, and reads its questions', async () => {
        const { script } = drawLibrary(first());

        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Product %2/ }));

        const opened = screen.getByRole('dialog', { name: /^library\.drawer\.title Product %2/ });
        assert.deepEqual(script.callsTo('suggestions'), [['product']]);
        assert.ok(within(opened).getByText('sku'));
        assert.ok(within(opened).getByText('Product pages, except configurable.'));
        assert.ok(within(opened).getByText(/^library\.blocks\.new/));
        assert.ok(within(opened).getAllByText(/^library\.questions\.loading/).length === 3);
        assert.ok(within(opened).queryByRole('alert') === null, 'a template the shop does not have warns of nothing');

        script.answer('suggestions', contractSuggestions('product'));

        const brand = await waitFor(() => within(drawer()).getByRole('combobox', { name: 'Brand' }));
        assert.ok(brand instanceof HTMLSelectElement);
        assert.equal(brand.value, 'attribute:manufacturer');
        assert.ok(within(drawer()).getByText('Filled for 31 of 184 products. The others get the shop name.'));
    });

    it('posts the answers chosen, the suggested one for a question left alone, with the key of the drawer', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Product %2/ }));
        script.answer('suggestions', contractSuggestions('product'));
        const brand = await waitFor(() => within(drawer()).getByRole('combobox', { name: 'Brand' }));

        fireEvent.change(brand, { target: { value: 'leave_out' } });
        const hint = within(drawer()).getByText('Google recommends a brand.');
        assert.equal(hint.closest('[data-tone]')?.getAttribute('data-tone'), 'warn');
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));

        assert.deepEqual(script.callsTo('add'), [[
            'product',
            { brand: 'leave_out', gtin: 'attribute:ean', description: 'attribute:short_description' },
            'key-1',
        ]]);
        assert.ok(within(drawer()).getByRole('button', { name: /library\.drawer\.adding/ }));

        script.answer('add', contractAdd('added'));

        await waitFor(() => assert.ok(screen.queryByRole('dialog') === null));
        const toast = screen.getByRole('status');
        assert.match(toast.textContent ?? '', /library\.toast\.added Product %2/);
        assert.equal(within(toast).getByRole('link', { name: /library\.toast\.open/ }).getAttribute('href'), `${EDITOR}?id=42`);
        assert.ok(within(cardOf('Product')).getByText(/^library\.card\.added/), 'the library as the add left it');
    });

    it('adds a template that asks nothing at once, and says a building block was added', async () => {
        const { script } = drawLibrary(first());

        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Shipping policy %2/ }));
        assert.ok(within(drawer()).getByText(/^library\.drawer\.block/));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));

        assert.deepEqual(script.callsTo('suggestions'), []);
        assert.deepEqual(script.callsTo('add'), [['shipping-policy', {}, 'key-1']]);
        script.answer('add', contractAdd('block'));
        await waitFor(() => assert.match(screen.getByRole('status').textContent ?? '', /library\.toast\.addedBlock Shipping policy/));
    });

    it('takes the suggested answers when they cannot be read, and reads them again when asked', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Organization %2/ }));

        script.refuse('suggestions', new RequestError('network'));

        await waitFor(() => assert.ok(within(drawer()).getByText(/^library\.questions\.failed/)));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.questions\.retry/ }));
        assert.deepEqual(script.callsTo('suggestions'), [['organization'], ['organization']]);
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));
        assert.deepEqual(script.callsTo('add'), [['organization', {}, 'key-1']], 'the server takes its suggested answers');
    });

    it('keeps the drawer open when the add is refused, saying why', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Article %2/ }));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));

        script.answer('add', contractAdd('refused'));

        await waitFor(() => assert.ok(within(drawer()).getByText(/^library\.refused\.other/)));
        assert.ok(screen.queryByRole('status') === null);
    });

    it('keeps the drawer open when the add fails, and repeats the same add when Add is pressed again', async () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Article %2/ }));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));

        script.refuse('add', new RequestError('timeout'));

        await waitFor(() => assert.ok(within(drawer()).getByRole('alert')));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));
        assert.deepEqual(script.callsTo('add').map((call) => call[2]), ['key-1', 'key-1']);
    });

    it('gives a drawer opened anew a key of its own, and closes on Cancel', () => {
        const { script } = drawLibrary(first());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Article %2/ }));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.cancel/ }));
        assert.ok(screen.queryByRole('dialog') === null);

        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addNamed Article %2/ }));
        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));

        assert.deepEqual(script.callsTo('add'), [['article', {}, 'key-2']]);
    });
});

describe('b5 · a template the shop has already', () => {
    it('warns that another copy is added, naming it', () => {
        drawLibrary(libraryData());

        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addAgainNamed FAQ %2/ }));

        assert.match(within(drawer()).getByRole('alert').textContent ?? '', /library\.again\.other FAQ FAQ 2/);
    });

    it('warns of a second product snippet in words of its own', () => {
        const { script } = drawLibrary(libraryData());

        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addAgainNamed Product %2/ }));

        assert.match(within(drawer()).getByRole('alert').textContent ?? '', /library\.again\.product/);
        assert.deepEqual(script.callsTo('suggestions'), [['product']]);
    });

    it('names the second copy in the message after it is added, linked to its editor', async () => {
        const { script } = drawLibrary(libraryData());
        fireEvent.click(screen.getByRole('button', { name: /^library\.card\.addAgainNamed Product %2/ }));
        script.answer('suggestions', contractSuggestions('product'));
        await waitFor(() => within(drawer()).getByRole('combobox', { name: 'Brand' }));

        fireEvent.click(within(drawer()).getByRole('button', { name: /library\.drawer\.add/ }));
        script.answer('add', contractAdd('again'));

        const toast = await waitFor(() => screen.getByRole('status'));
        assert.match(toast.textContent ?? '', /library\.toast\.added Product 2/);
        assert.equal(within(toast).getByRole('link').getAttribute('href'), `${EDITOR}?id=43`);
    });

    it('opens the drawer of the template the address names, offering its newer version beside the copy', () => {
        drawLibrary(libraryData(libraryChanging('faq', { hasNewerVersion: true }), 'faq'));

        assert.match(within(drawer()).getByRole('alert').textContent ?? '', /library\.drawer\.newer FAQ 2/);
        assert.ok(within(drawer()).getByRole('button', { name: /library\.drawer\.addNewer/ }));
    });
});

describe('b6 · b7 · the library loading and failed', () => {
    it('says a library the server could not read failed, and reads it again on Try again', async () => {
        const { script } = drawLibrary(unavailableData());

        assert.ok(screen.getByText(/^library\.failed\.title/));
        assert.ok(screen.getByText(/^page\.unavailable/));
        assert.equal(search().disabled, true);
        assert.equal(screen.getByRole('link', { name: /library\.header\.blank/ }).getAttribute('href'), NEW, 'a blank snippet still starts');

        fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

        assert.equal(script.callsTo('list').length, 1);
        assert.ok(screen.getByText(/^page\.loading/));
        assert.ok(document.querySelector('[aria-busy="true"]'));
        assert.equal(document.querySelectorAll('.rs-library__skeleton-card').length, 6);
        script.answer('list', contractLibrary());
        await waitFor(() => assert.equal(cardNames().length, 13));
        assert.equal(search().disabled, false);
    });

    it('keeps saying it failed when the read fails again', async () => {
        const { script } = drawLibrary(unavailableData());
        fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

        script.refuse('list', new RequestError('server'));

        await waitFor(() => assert.ok(screen.getByText(/^page\.unavailable/)));
        assert.ok(screen.getByText(/^library\.failed\.text/));
    });
});

describe('an admin who may only look', () => {
    it('sees every template without an add button, a recommended set or a blank snippet', () => {
        drawLibrary(first(), { canChange: false });

        assert.equal(cardNames().length, 13);
        assert.ok(screen.queryByRole('button', { name: /library\.card\.add/ }) === null);
        assert.ok(screen.queryByRole('region', { name: /library\.hero\.title/ }) === null);
        assert.ok(screen.queryByRole('link', { name: /library\.header\.blank/ }) === null);
        assert.ok(screen.queryByRole('link', { name: /library\.notIn\.blank/ }) === null);
    });

    it('does not open the drawer the address names', () => {
        drawLibrary(libraryData(contractLibrary(), 'faq'), { canChange: false });

        assert.ok(screen.queryByRole('dialog') === null);
    });
});
