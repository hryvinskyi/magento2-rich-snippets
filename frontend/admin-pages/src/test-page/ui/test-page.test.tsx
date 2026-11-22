/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Test page drawn from the server's document, with endpoints, a clock and an address the tests script: each
 * frame of the design (t1–t10; t11 is the narrow layout, which the stylesheet draws), the page picker, a test
 * replaced by a newer one, the polling of a check on its way and its cleanup, the drawer of a finding, the download,
 * and what an admin who may not run tests sees.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/preact';
import { render } from 'preact';
import { Icon, RequestError } from '@hryvinskyi/admin-ui';
import { drawTest } from '../../test/test-page-harness.tsx';
import {
    contractFindings,
    contractItems,
    contractRun,
    contractSearch,
    contractStatus,
    testData,
    TEST_ROUTES,
} from '../../test/test-page-fixtures.ts';

afterEach(cleanup);

const EDITOR = TEST_ROUTES['hryvinskyi_richsnippets/snippets/edit'] ?? '';
const SETTINGS = TEST_ROUTES['adminhtml/system_config/edit'] ?? '';
const PRODUCT = TEST_ROUTES['catalog/product/edit'] ?? '';
const PRODUCTS = TEST_ROUTES['catalog/product/index'] ?? '';

const outputCard = (): HTMLElement => {
    const card = screen.getByRole('heading', { name: /test\.output\.title/ }).closest('section');
    assert.ok(card instanceof HTMLElement);
    return card;
};

const subtitle = (): string => outputCard().querySelector('.hui-card__subtitle')?.textContent ?? '';

const line = (): string => document.querySelector('.rs-test__bar-line')?.textContent ?? '';

const toast = (): HTMLElement | null => document.querySelector<HTMLElement>('.hui-strip');

const drawer = (): HTMLElement => {
    const panel = document.querySelector<HTMLElement>('.hui-drawer [role="dialog"]');
    assert.ok(panel !== null, 'the drawer is open');
    return panel;
};

const options = (): HTMLElement[] => within(screen.getByRole('listbox', { hidden: true })).queryAllByRole('option');

const picker = (): HTMLInputElement => {
    const input = screen.getByRole('combobox', { name: /test\.bar\.page/ });
    assert.ok(input instanceof HTMLInputElement);
    return input;
};

describe('t1 · a product page that meets everything', () => {
    it('tests the first page on opening and shows every block, the first open, with the results beside them', async () => {
        const { script } = drawTest(testData('page'));

        assert.deepEqual(script.callsTo('run'), [['product:12', 1]]);
        assert.ok(screen.getByLabelText(/test\.output\.busy/), 'three placeholder rows while the page is read');
        assert.match(line(), /test\.bar\.line\.busy \/bags\/field-satchel\.html/);
        script.answer('run', contractRun('done'));

        await waitFor(() => assert.match(subtitle(), /test\.output\.subtitle \/bags\/field-satchel\.html test\.output\.blocks\.other 2 1\.4/));
        const heads = within(outputCard()).getAllByRole('button', { expanded: true });
        assert.equal(heads.length, 1, 'the first block is open');
        assert.ok(screen.getByRole('group', { name: /test\.block\.output Product/ }));
        assert.match(line(), /test\.bar\.line\.done 0\.4/);
        assert.equal(within(outputCard()).getByRole('link', { name: /test\.block\.edit/ }).getAttribute('href'), `${EDITOR}?id=3`);
        assert.match(outputCard().textContent ?? '', /test\.block\.foot\.other 60/);
        const panel = screen.getByRole('heading', { name: /test\.req\.title/ }).closest('.hui-panel');
        assert.ok(panel instanceof HTMLElement);
        assert.equal(panel.querySelectorAll('.rs-test__feature').length, 3);
        assert.match(panel.textContent ?? '', /Merchant listing.*test\.req\.met/);
        assert.equal(panel.querySelector('.rs-test__chip')?.textContent, 'offers.shippingDetails');
    });

    it('opens another block, lists the snippets not output with their reasons, and links to the conditions', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('done'));
        const breadcrumbs = await waitFor(() => within(outputCard()).getByRole('button', { name: /Breadcrumbs/ }));

        fireEvent.click(breadcrumbs);

        assert.equal(breadcrumbs.getAttribute('aria-expanded'), 'true');
        const list = outputCard().querySelector('details');
        assert.ok(list !== null && !list.open, 'closed while the page carries blocks');
        assert.match(list.querySelector('summary')?.textContent ?? '', /test\.notOutput\.summary\.other 5/);
        assert.match(list.textContent ?? '', /Product with variants.*Set for product type Configurable; this one is Simple\./);
        assert.equal(within(list).getByRole('link', { name: /test\.notOutput\.conditions/ }).getAttribute('href'), `${EDITOR}?id=4&section=pages`);
    });

    it('shows the theme\'s own markup with the way to remove it in Settings', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('done'));

        const notice = await waitFor(() => {
            const found = outputCard().querySelector('.rs-test__theme');
            assert.ok(found instanceof HTMLElement);
            return found;
        });

        assert.match(notice.textContent ?? '', /test\.theme\.heading.*The theme writes its own Product microdata/);
        assert.equal(within(notice).getByRole('link', { name: /test\.theme\.remove/ }).getAttribute('href'), `${SETTINGS}?section=hryvinskyi_rich_snippets`);
    });
});

describe('t2–t3 · a page with gaps and a page with a problem', () => {
    it('marks a block with a problem, says what is missing, and sets the required properties apart', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('problem'));

        await waitFor(() => assert.match(subtitle(), /test\.output\.subtitleProblems .* test\.output\.problems\.other 2/));
        assert.equal(outputCard().querySelectorAll('.rs-test__block[data-bad="true"]').length, 2);
        assert.ok(within(outputCard()).getByText('Merchant listing: image missing.'));
        assert.ok(within(outputCard()).getByText('test.block.notOurs %1 %2 %3'), 'a block no snippet wrote');
        const required = document.querySelector('.rs-test__chip[data-required="true"]');
        assert.equal(required?.textContent, 'image');
        assert.match(document.querySelector('.rs-test__feature:nth-child(2)')?.textContent ?? '', /test\.req\.leftOut/);
    });
});

describe('a page two snippets of one type are output on', () => {
    it('lists each kind of result once against Google\'s requirements, as the server merged them', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('twice'));

        const panel = await waitFor(() => {
            const found = screen.getByRole('heading', { name: /test\.req\.title/ }).closest('.hui-panel');
            assert.ok(found instanceof HTMLElement);
            assert.equal(found.querySelectorAll('.rs-test__feature').length, 2);
            return found;
        });

        assert.equal((panel.textContent ?? '').split('Merchant listing').length - 1, 1, 'one Merchant listing row');
        assert.equal(panel.querySelector('.rs-test__chip[data-required="true"]')?.textContent, 'image');
    });
});

describe('t9 · a page that outputs nothing', () => {
    it('says nothing is output and opens the reasons, snippet by snippet', async () => {
        const { script } = drawTest(testData('page', { page: { ref: null, kind: 'other', label: '/checkout/cart', path: '/checkout/cart' } }));
        assert.deepEqual(script.callsTo('run'), [['/checkout/cart', 1]]);
        script.answer('run', contractRun('nothing'));

        await waitFor(() => assert.ok(within(outputCard()).getByText(/test\.nothing\.title/)));
        assert.ok(within(outputCard()).getByText(/test\.nothing\.address/));
        assert.match(subtitle(), /test\.output\.subtitleNothing \/checkout\/cart/);
        assert.ok(outputCard().querySelector('details')?.open, 'open by default');
        assert.match(document.querySelector('.hui-panel')?.textContent ?? '', /test\.req\.nothing/);
    });
});

describe('t10 · the page could not be read', () => {
    it('says why and what still works, and tests again on Try again', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('failed'));

        await waitFor(() => assert.ok(within(outputCard()).getByText(/test\.fail\.title/)));
        assert.ok(within(outputCard()).getByText(/The storefront answered 503/));
        assert.match(line(), /test\.bar\.line\.failed/);
        assert.match(document.querySelector('.hui-panel')?.textContent ?? '', /test\.req\.unread/);
        fireEvent.click(within(outputCard()).getByRole('button', { name: /test\.fail\.retry/ }));
        assert.equal(script.callsTo('run').length, 2);
    });

    it('says when the server could not run the test, in the place\'s words', async () => {
        const { script } = drawTest(testData('page'));
        script.refuse('run', new RequestError('server'));

        await waitFor(() => assert.ok(within(outputCard()).getByText(/test\.error\.text/)));
        assert.match(line(), /test\.bar\.line\.error/);
    });
});

describe('the page bar', () => {
    it('tests again in another store view and drops the answer of the test it replaced', async () => {
        const { script } = drawTest(testData('page'));
        const first = script.calls.find((call) => call.method === 'run');

        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '2' } });

        assert.deepEqual(script.callsTo('run'), [['product:12', 1], ['product:12', 2]]);
        assert.ok(first?.signal.aborted, 'the replaced test is aborted');
        script.answer('run', contractRun('nothing'));
        script.answer('run', contractRun('done'));
        await waitFor(() => assert.match(subtitle(), /test\.output\.blocks\.other 2/));
        assert.deepEqual([...(script.addresses.at(-1) ?? [])], [['page', 'product:12'], ['store', '2']]);
    });

    it('searches every page when the picker opens, waits for typing to rest, and tests the page picked', async () => {
        const { script } = drawTest(testData('page'));
        script.answer('run', contractRun('done'));

        fireEvent.focus(picker());
        assert.deepEqual(script.callsTo('search'), [['', 1]]);
        fireEvent.input(picker(), { target: { value: '/checkout/cart' } });
        assert.deepEqual(script.clock.waiting(), [250]);
        script.clock.run();
        assert.deepEqual(script.callsTo('search')[1], ['/checkout/cart', 1]);
        script.answer('search', contractSearch('pages'));
        script.answer('search', contractSearch('address'));
        const option = await waitFor(() => within(screen.getByRole('listbox')).getByRole('option', { name: /test\.picker\.kind\.other \/checkout\/cart/ }));
        assert.equal(options().length, 1, 'only the newest search answers the list');

        fireEvent.mouseDown(option);

        assert.deepEqual(script.callsTo('run').at(-1), ['/checkout/cart', 1]);
        assert.match(picker().value, /test\.picker\.kind\.other \/checkout\/cart/);
        assert.equal(options().length, 0, 'the list closes');
    });

    it('moves through the list with the arrow keys, picks with Enter, and closes with Escape as it was', async () => {
        const { script } = drawTest(testData('page'));
        fireEvent.focus(picker());
        script.answer('search', contractSearch('pages'));
        await waitFor(() => assert.equal(options().length, 2));

        fireEvent.keyDown(picker(), { key: 'ArrowDown' });
        fireEvent.keyDown(picker(), { key: 'ArrowDown' });
        assert.equal(options()[1]?.getAttribute('aria-selected'), 'true');
        fireEvent.keyDown(picker(), { key: 'Enter' });
        assert.deepEqual(script.callsTo('run').at(-1), ['product:12', 1]);

        fireEvent.focus(picker());
        fireEvent.keyDown(picker(), { key: 'Escape' });
        assert.match(picker().value, /test\.picker\.kind\.product Field satchel/);
    });
});

describe('t5–t6 · the whole catalog and the pages behind a finding', () => {
    it('shows where the check stands, its facts, and the findings worst first', () => {
        drawTest(testData('catalog', { finding: null }));

        assert.ok(screen.getByText('2 products are invalid'));
        assert.ok(screen.getByText('Last run today at 03:00, in 41 seconds.'));
        assert.equal(document.querySelectorAll('.rs-test__facts .hui-facts__item').length, 4);
        assert.ok(screen.getByRole('button', { name: /test\.check\.run/ }));
        const rows = document.querySelectorAll('.rs-test__finding');
        assert.equal(rows.length, 4);
        assert.equal(rows[0]?.getAttribute('aria-label'), 'No image: 2 products. Show them.');
        assert.match(rows[1]?.textContent ?? '', /EAN is empty.*Product and Product with variants.*23/);
        assert.ok(screen.getByText(/It runs every night at 03:00/), 'when the check runs by itself');
    });

    it('opens the finding the address names, lists its pages with their links, and tests one', async () => {
        const { script } = drawTest(testData('catalog'));

        assert.deepEqual(script.callsTo('findingItems'), [['merchant-listing-gtin13-product-ean', null, 1]]);
        script.answer('findingItems', contractItems('first'));
        const row = await waitFor(() => within(drawer()).getByRole('row', { name: /Key fob/ }));
        assert.ok(within(drawer()).getByText(/Fill EAN where there is a value/));
        assert.equal(within(row).getByRole('link', { name: 'Key fob' }).getAttribute('href'), `${PRODUCT}?id=32&store=1`);
        assert.ok(within(drawer()).getByText('The first 3 of 23. The download has all of them.'));
        assert.equal(within(drawer()).getByRole('link', { name: /test\.drawer\.open/ }).getAttribute('href'), PRODUCTS);

        fireEvent.click(within(row).getByRole('button', { name: /test\.drawer\.testNamed Key fob/ }));

        assert.deepEqual(script.callsTo('run'), [['product:32', 1]]);
        assert.ok(document.querySelector('.hui-drawer') === null, 'the drawer closes');
        assert.equal(document.querySelector('.rs-test')?.getAttribute('data-mode'), 'page');
        assert.match(line(), /test\.bar\.line\.busy Key fob/);
    });

    it('shows the first pages with the line that says so, no pager, and saves every page, saying so', async () => {
        const { script } = drawTest(testData('catalog'));
        script.answer('findingItems', contractItems('first'));
        await waitFor(() => within(drawer()).getByRole('row', { name: /Key fob/ }));

        assert.ok(within(drawer()).getByText('The first 3 of 23. The download has all of them.'));
        assert.equal(within(drawer()).queryByRole('button', { name: 'kit.pager.next' }), null);
        fireEvent.click(within(drawer()).getByRole('button', { name: /test\.drawer\.download/ }));
        assert.deepEqual(script.callsTo('download'), [['merchant-listing-gtin13-product-ean', null]]);
        script.answer('download', null);

        await waitFor(() => assert.equal(toast()?.textContent, '23 products were saved as rich-snippets-merchant-listing-gtin13-product-ean.csv.'));
    });

    it('says in the drawer when the list could not be saved', async () => {
        const { script } = drawTest(testData('catalog'));
        script.answer('findingItems', contractItems('all'));
        await waitFor(() => within(drawer()).getByRole('row', { name: /Key fob/ }));
        fireEvent.click(within(drawer()).getByRole('button', { name: /test\.drawer\.download/ }));

        script.refuse('download', new RequestError('network'));

        await waitFor(() => assert.ok(within(drawer()).getByText(/test\.drawer\.downloadFailed/)));
    });
});

describe('t7–t8 · a check on its way, and nothing found', () => {
    it('asks where a running check stands every three seconds until it ends, then reads the findings and says so', async () => {
        const { script } = drawTest(testData('catalog', { finding: null, status: contractStatus('running') }));

        assert.ok(screen.getByRole('progressbar', { name: /test\.check\.progress/ }));
        assert.ok(screen.getByText(/test\.findings\.waitingTitle/));
        assert.ok(screen.getByRole('button', { name: /test\.check\.running/ }).hasAttribute('disabled'));
        assert.deepEqual(script.clock.waiting(), [3000]);
        script.clock.run();
        script.answer('status', contractStatus('running'));
        await waitFor(() => assert.deepEqual(script.clock.waiting(), [3000]));
        script.clock.run();
        script.answer('status', contractStatus('clean'));

        await waitFor(() => assert.equal(toast()?.textContent, 'The catalog was checked: 207 pages in 3 store views.'));
        assert.deepEqual(script.callsTo('findings'), [[null]]);
        assert.deepEqual(script.clock.waiting(), [], 'no question once the check has ended');
        script.answer('findings', contractFindings('none'));
        await waitFor(() => assert.ok(screen.getByText(/test\.findings\.cleanTitle/)));
    });

    it('cancels the next question when the page goes away', () => {
        const { script, unmount } = drawTest(testData('catalog', { finding: null, status: contractStatus('waiting') }));
        assert.deepEqual(script.clock.waiting(), [3000]);

        unmount();

        assert.deepEqual(script.clock.waiting(), []);
    });

    it('stops asking with a message when the session ended', async () => {
        const { script } = drawTest(testData('catalog', { finding: null, status: contractStatus('running') }));
        script.clock.run();
        script.refuse('status', new RequestError('expired'));

        await waitFor(() => assert.ok(toast() !== null));
        assert.deepEqual(script.clock.waiting(), []);
    });

    it('asks for a check on Check now and watches it', async () => {
        const { script } = drawTest(testData('catalog', { finding: null }));

        fireEvent.click(screen.getByRole('button', { name: /test\.check\.run/ }));
        assert.equal(script.callsTo('requestCheck').length, 1);
        script.answer('requestCheck', contractStatus('waiting'));

        await waitFor(() => assert.ok(screen.getByText('Waiting for the next cron run. It starts within a minute when cron is running.')));
        await waitFor(() => assert.deepEqual(script.clock.waiting(), [3000]));
    });

    it('says before any check that the findings come after the first', () => {
        drawTest(testData('catalog', { finding: null, status: contractStatus('none'), findings: contractFindings('none') }));

        assert.ok(screen.getByText('No check has run yet'));
        assert.ok(screen.getByText(/test\.findings\.noneTitle/));
    });
});

describe('the modes and the right to run tests', () => {
    it('switches to the whole catalog and back, writing what it shows into the address', async () => {
        const { script } = drawTest(testData('page'));

        fireEvent.click(screen.getByRole('button', { name: /test\.mode\.catalog/ }));

        assert.ok(screen.getByRole('heading', { name: /test\.findings\.title/ }));
        await waitFor(() => assert.deepEqual([...(script.addresses.at(-1) ?? [])], [['mode', 'catalog']]));
        fireEvent.click(screen.getByRole('button', { name: /test\.mode\.page/ }));
        assert.ok(screen.getByRole('heading', { name: /test\.output\.title/ }));
    });

    it('draws each mode with its glyph before its name', () => {
        drawTest(testData('page'));
        const glyph = (name: string): string | null => {
            const host = document.createElement('div');
            render(<Icon name={name} />, host);
            return host.querySelector('path')?.getAttribute('d') ?? null;
        };
        const drawn = (mode: RegExp): string | null | undefined =>
            screen.getByRole('button', { name: mode }).querySelector('svg[aria-hidden="true"] path')?.getAttribute('d');

        assert.equal(drawn(/test\.mode\.page/), glyph('web'));
        assert.equal(drawn(/test\.mode\.catalog/), glyph('inventory'));
    });

    it('tests nothing for an admin who may not run tests, and shows the catalog without its actions', async () => {
        const { script } = drawTest(testData('catalog'), { canTest: false });
        script.answer('findingItems', contractItems('first'));
        await waitFor(() => within(drawer()).getByRole('row', { name: /Key fob/ }));

        assert.ok(screen.queryByRole('group', { name: /test\.mode\.label/ }) === null);
        assert.ok(screen.queryByRole('button', { name: /test\.check\.run/ }) === null);
        assert.ok(within(drawer()).queryByRole('button', { name: /test\.drawer\.test/ }) === null);
        assert.deepEqual(script.callsTo('run'), []);
    });

    it('opens a page link only for an admin who may open its screen', async () => {
        const { script } = drawTest(testData('catalog'), { without: ['catalog/product/edit', 'catalog/product/index'] });
        script.answer('findingItems', contractItems('first'));
        const row = await waitFor(() => within(drawer()).getByRole('row', { name: /Key fob/ }));

        assert.ok(within(row).queryByRole('link') === null);
        assert.ok(within(drawer()).queryByRole('link', { name: /test\.drawer\.open/ }) === null);
    });
});
