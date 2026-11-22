/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Overview drawn from the contract's states, found the way a person finds things: by role and name. Each design
 * state has its case — live with nothing to fix, live with things to fix, one store view, nothing added, all off,
 * loading, failed, and the narrow page — and what the admin's role leaves out has its own.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/preact';
import { RequestError } from '@hryvinskyi/admin-ui';
import type { OverviewData } from '../domain/model.ts';
import type { OverviewEndpoints } from '../adapters/overview-endpoints.ts';
import { parseOverviewData } from '../adapters/overview-data.ts';
import { drawOverview } from '../../test/harness.tsx';
import { ROUTES, stateData } from '../../test/fixtures.ts';

afterEach(cleanup);

interface Pending {
    readonly endpoints: OverviewEndpoints;
    readonly asked: (number | null)[];
    answer(data: OverviewData): void;
    refuse(error: unknown): void;
}

/** An endpoint whose answers the test hands over one at a time. */
function pendingEndpoint(): Pending {
    const asked: (number | null)[] = [];
    const waiting: { resolve: (data: OverviewData) => void; reject: (error: unknown) => void }[] = [];
    return {
        asked,
        endpoints: {
            read: (store: number | null): Promise<OverviewData> => {
                asked.push(store);
                return new Promise((resolve, reject) => waiting.push({ resolve, reject }));
            },
        },
        answer: (data: OverviewData): void => waiting.shift()?.resolve(data),
        refuse: (error: unknown): void => waiting.shift()?.reject(error),
    };
}

const googleCard = (): HTMLElement => {
    const heading = screen.getByRole('heading', { level: 3, name: /overview\.features\.title/ });
    const card = heading.closest('section');
    assert.ok(card instanceof HTMLElement);
    return card;
};

describe('v1 · live, nothing to fix', () => {
    it('draws the header, the tabs, the store bar, the status, the page map, the Google card and the side panels', () => {
        drawOverview(stateData('healthy'));

        assert.ok(screen.getByRole('heading', { level: 1, name: 'Overview' }));
        assert.equal(screen.getByRole('link', { name: 'Overview' }).getAttribute('aria-current'), 'page');
        assert.ok(screen.getByText('7 snippets on'));
        assert.equal(screen.getByRole('link', { name: /page\.storefront/ }).getAttribute('href'), 'https://shop.test/en/');
        assert.ok(screen.getByRole('combobox', { name: /page\.store\.label/ }));
        assert.ok(screen.getByText(/overview\.store\.allLine/));
        assert.ok(screen.getByText('Live'));
        assert.ok(screen.getByText('7 snippets on 4 kinds of page, in 3 store views.'));
        assert.ok(screen.getByText('Pages with snippets'));
        assert.ok(screen.getByRole('heading', { level: 3, name: /overview\.kinds\.title/ }));
        assert.equal(screen.getAllByRole('progressbar').length, 4);
        assert.ok(within(googleCard()).getByText('No result'));
        assert.ok(screen.getByText(/Nothing\. Every page meets what Google requires/));
        assert.ok(screen.getByRole('heading', { level: 3, name: /overview\.source\.title/ }));
    });

    it('links the status action and every kind of page with a page to show to the Test page', () => {
        drawOverview(stateData('healthy'));
        const test = ROUTES['hryvinskyi_richsnippets/test/index'] ?? '';

        assert.equal(screen.getByRole('link', { name: 'Test a page' }).getAttribute('href'), test);
        const see = screen.getAllByRole('link', { name: /overview\.kinds\.see/ }).map((link) => link.getAttribute('href'));
        assert.deepEqual(see, [`${test}?page=home`, `${test}?page=product%3A12`, `${test}?page=category%3A3`, `${test}?page=cms%3A4`]);
    });

    it('draws a snippet that is off as off, beside the ones that are on', () => {
        drawOverview(stateData('healthy'));

        const off = screen.getByText('Product list').closest('.rs-overview__chip');
        assert.ok(off?.classList.contains('rs-overview__chip--off'));
        assert.equal(screen.getByText('Website name').closest('.rs-overview__chip')?.classList.contains('rs-overview__chip--off'), false);
    });

    it('reads the store view picked and says what the choice means', async () => {
        const pending = pendingEndpoint();
        drawOverview(stateData('healthy'), { endpoints: pending.endpoints });

        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '2' } });

        assert.deepEqual(pending.asked, [2]);
        assert.ok(screen.getByText('overview.store.oneLine Deutsch'));
        assert.equal(screen.getByRole('link', { name: /page\.storefront/ }).getAttribute('href'), 'https://shop.test/de/');
        pending.answer(parseOverviewData(stateData('deutsch'), '$'));
        await waitFor(() => assert.ok(screen.getByText('6 snippets on')));
    });
});

describe('v2 · live, things to fix', () => {
    it('says the pages are invalid and lists each thing with the screen that fixes it', () => {
        drawOverview(stateData('attention'));

        assert.ok(screen.getByText('2 products are invalid'));
        const panel = screen.getByRole('heading', { level: 3, name: /overview\.attention\.title/ }).closest('section');
        assert.ok(panel instanceof HTMLElement);
        assert.ok(within(panel).getByText('4'));
        assert.equal(within(panel).getAllByRole('listitem').length, 4);
        const test = ROUTES['hryvinskyi_richsnippets/test/index'] ?? '';
        assert.equal(
            within(panel).getAllByRole('link', { name: 'See them' })[0]?.getAttribute('href'),
            `${test}?mode=catalog&finding=merchant-listing-image`,
        );
        assert.equal(
            within(panel).getByRole('link', { name: 'Open Settings' }).getAttribute('href'),
            `${ROUTES['adminhtml/system_config/edit'] ?? ''}?section=hryvinskyi_rich_snippets`,
        );
        assert.equal(
            within(panel).getByRole('link', { name: 'Open the library' }).getAttribute('href'),
            `${ROUTES['hryvinskyi_richsnippets/library/index'] ?? ''}?template=product`,
        );
        assert.ok(within(googleCard()).getByText('Partly'));
        assert.ok(screen.getByText('Still there').classList.contains('rs-overview__fact--warn'));
    });
});

describe('v3 · one store view', () => {
    it('opens on the store view of its data and leaves out what that store view does not output', () => {
        drawOverview(stateData('deutsch'));

        const picker = screen.getByRole('combobox', { name: /page\.store\.label/ });
        assert.ok(picker instanceof HTMLSelectElement);
        assert.equal(picker.value, '2');
        assert.ok(screen.getByText('overview.store.oneLine Deutsch'));
        assert.equal(screen.queryByText('FAQ'), null);
        assert.equal(screen.getByRole('link', { name: 'Test a page' }).getAttribute('href'), `${ROUTES['hryvinskyi_richsnippets/test/index'] ?? ''}?store=2`);
    });
});

describe('v4 · nothing added yet', () => {
    it('draws the first-visit hero alone, with the way in through the library or a blank snippet', () => {
        drawOverview(stateData('none'));

        assert.ok(screen.getByRole('heading', { level: 2, name: /overview\.hero\.title/ }));
        assert.equal(
            screen.getByRole('link', { name: /overview\.hero\.library/ }).getAttribute('href'),
            ROUTES['hryvinskyi_richsnippets/library/index'],
        );
        assert.equal(
            screen.getByRole('link', { name: /overview\.hero\.blank/ }).getAttribute('href'),
            ROUTES['hryvinskyi_richsnippets/snippets/new'],
        );
        assert.equal(screen.getAllByRole('listitem').length, 3);
        assert.ok(screen.getByText(/overview\.hero\.step1\.title/));
        assert.ok(screen.getByText('Nothing is output'));
        assert.equal(screen.queryByRole('combobox'), null, 'no store bar');
        assert.equal(screen.queryByText('Nothing is output yet'), null, 'no status card');
        assert.equal(screen.queryByRole('heading', { level: 3, name: /overview\.kinds\.title/ }), null);
    });
});

describe('v5 · added, all switched off', () => {
    it('says it is off, moves the merchant on to the snippets, and shows the Google card\'s note', () => {
        drawOverview(stateData('off'));

        assert.ok(screen.getByText('Off'));
        assert.ok(screen.getByText('All snippets off'));
        const open = screen.getByRole('link', { name: 'Open Snippets' });
        assert.equal(open.getAttribute('href'), ROUTES['hryvinskyi_richsnippets/snippets/index']);
        assert.ok(open.classList.contains('hui-button--primary'));
        assert.ok(within(googleCard()).getByText('Nothing to show yet'));
        assert.ok(within(googleCard()).getByText('Nothing while the snippets are off.'));
        assert.equal(screen.queryAllByRole('link', { name: /overview\.kinds\.see/ }).length, 0);
        assert.equal(screen.getAllByText('Product')[0]?.closest('.rs-overview__chip')?.classList.contains('rs-overview__chip--off'), true);
        assert.ok(screen.getByText('The theme still writes its own Product markup'));
    });
});

describe('v6 · loading', () => {
    it('shows the Google card loading while the status, the page map and the list stay', () => {
        const pending = pendingEndpoint();
        drawOverview(stateData('attention'), { endpoints: pending.endpoints });

        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '1' } });

        const card = googleCard();
        assert.ok(within(card).getByLabelText(/page\.loadingLabel/));
        assert.ok(within(card).getByText(/page\.loading/));
        assert.equal(within(card).queryByRole('progressbar'), null);
        assert.ok(screen.getByText('2 products are invalid'));
        assert.ok(screen.getByRole('heading', { level: 3, name: /overview\.kinds\.title/ }));
        assert.equal(screen.getAllByRole('link', { name: 'See them' }).length, 2);
    });
});

describe('v7 · the numbers could not be loaded', () => {
    it('says so in the Google card, keeps the rest, and loads them again on Try again', async () => {
        const pending = pendingEndpoint();
        drawOverview(stateData('attention'), { endpoints: pending.endpoints, logger: { error: () => undefined } });
        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '1' } });

        pending.refuse(new RequestError('network'));

        const alert = await screen.findByRole('alert');
        assert.ok(within(alert).getByText(/overview\.features\.failedTitle/));
        assert.ok(within(alert).getByText(/overview\.features\.failedText/));
        assert.ok(within(googleCard()).getByText(/page\.unavailable/));
        assert.ok(screen.getByText('2 products are invalid'));

        fireEvent.click(within(alert).getByRole('button', { name: 'Try again' }));

        assert.deepEqual(pending.asked, [1, 1]);
        pending.answer(parseOverviewData(stateData('healthy'), '$'));
        await waitFor(() => assert.equal(within(googleCard()).getAllByRole('progressbar').length, 4));
    });

    it('offers no retry when the session has ended, and says what to do instead', async () => {
        const pending = pendingEndpoint();
        drawOverview(stateData('healthy'), { endpoints: pending.endpoints, logger: { error: () => undefined } });
        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '1' } });

        pending.refuse(new RequestError('expired'));

        const alert = await screen.findByRole('alert');
        assert.ok(within(alert).getByText(/kit\.failure\.expired/), 'the kit words an ended session');
        assert.equal(within(alert).queryByRole('button'), null);
    });
});

describe('v8 · the narrow page', () => {
    it('puts the side panels after the main column, so they drop under it when the page is narrow', () => {
        drawOverview(stateData('attention'));

        const main = screen.getByRole('heading', { level: 3, name: /overview\.kinds\.title/ });
        const aside = screen.getByRole('complementary');
        assert.ok(within(aside).getByRole('heading', { level: 3, name: /overview\.attention\.title/ }));
        assert.ok(main.compareDocumentPosition(aside) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
});

describe('what the admin\'s role leaves out', () => {
    it('draws no Test tab, no Test a page and no See one without the right to run tests', () => {
        drawOverview(stateData('healthy'), { without: ['hryvinskyi_richsnippets/test/index'] });

        assert.equal(screen.queryByRole('link', { name: 'Test' }), null);
        assert.equal(screen.queryByRole('link', { name: 'Test a page' }), null);
        assert.equal(screen.queryAllByRole('link', { name: /overview\.kinds\.see/ }).length, 0);
    });

    it('lists the theme\'s markup without a link to the settings when the admin may not change them', () => {
        drawOverview(stateData('attention'), { without: ['adminhtml/system_config/edit'] });

        assert.ok(screen.getByText('The theme still writes its own Product markup'));
        assert.equal(screen.queryByRole('link', { name: 'Open Settings' }), null);
    });

    it('offers no blank snippet to an admin who may not change snippets', () => {
        drawOverview(stateData('none'), { without: ['hryvinskyi_richsnippets/snippets/new'] });

        assert.ok(screen.getByRole('link', { name: /overview\.hero\.library/ }));
        assert.equal(screen.queryByRole('link', { name: /overview\.hero\.blank/ }), null);
    });
});

describe('the page\'s wording', () => {
    it('reads only phrases its contract lists', () => {
        const missing = new Set<string>();
        for (const state of ['healthy', 'attention', 'none', 'off'] as const) {
            drawOverview(stateData(state), { missing });
            cleanup();
        }

        assert.deepEqual([...missing], []);
    });
});
