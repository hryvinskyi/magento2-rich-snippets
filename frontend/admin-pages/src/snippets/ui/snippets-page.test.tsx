/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Snippets page drawn from the contract's list and driven the way a person drives it, found by role and name:
 * every snippet with its chips, one store view, the snippets that need attention, the row menu with its copy, export
 * and delete, a building block in use, the import drawer with a good and a bad file, the first visit, loading, a list
 * that could not be read, and what an admin who may only look is left with. The narrow page is a container query of
 * the stylesheet, which a document without layout cannot apply; the browser check covers it.
 */
import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/preact';
import { RequestError } from '@hryvinskyi/admin-ui';
import { drawSnippets } from '../../test/snippets-harness.tsx';
import {
    contractList,
    emptyList,
    importPreview,
    listChanging,
    listWith,
    snippetsData,
    SNIPPETS_ROUTES,
} from '../../test/snippets-fixtures.ts';

afterEach(cleanup);

const rowOf = (name: string): HTMLElement => {
    const item = screen.getByText(name, { selector: '.rs-snippets__name' }).closest('li');
    assert.ok(item instanceof HTMLElement, `a row named ${name}`);
    return item;
};

const rowNames = (): readonly string[] =>
    [...document.querySelectorAll('.rs-snippets__name')].map((name) => name.textContent ?? '');

const chip = (name: RegExp): HTMLElement => within(screen.getByRole('group', { name: /snippets\.chips\.label/ })).getByRole('button', { name });

const writeAnswer = (list: Record<string, unknown> = contractList(), refused: unknown = null): Record<string, unknown> =>
    ({ ...list, id: null, refused });

describe('l1 · every snippet', () => {
    it('draws the header actions, the store bar, the card with its chips and rows, and the side panels', () => {
        const { script } = drawSnippets(snippetsData());

        assert.ok(screen.getByRole('heading', { level: 1, name: 'Snippets' }));
        assert.equal(
            screen.getByRole('link', { name: /snippets\.header\.blank/ }).getAttribute('href'),
            SNIPPETS_ROUTES['hryvinskyi_richsnippets/snippets/new'],
        );
        assert.equal(
            screen.getByRole('link', { name: /snippets\.header\.library/ }).getAttribute('href'),
            SNIPPETS_ROUTES['hryvinskyi_richsnippets/library/index'],
        );
        assert.ok(screen.getByRole('combobox', { name: /page\.store\.label/ }));
        assert.ok(screen.getByText(/snippets\.store\.allLine/));
        assert.ok(screen.getByText(/^snippets\.card\.subtitleOff 4 1 snippets\.card\.blocks\.one 1/));
        assert.deepEqual(rowNames(), ['Organization', 'Product', 'Product list', 'FAQ', 'Return policy', 'Product (imported)']);
        assert.equal(screen.getAllByRole('switch').length, 5, 'a switch on every page snippet, none on the block');
        assert.ok(within(rowOf('Return policy')).getByText(/^snippets\.blocks\.usedIn\.other 2/));
        assert.ok(screen.getByText('A snippet that is on writes its block into the page head.'));
        assert.ok(screen.getByRole('button', { name: /snippets\.move\.export/ }));
        assert.deepEqual(script.errors, [], 'every phrase the page asked for is in its contract');
    });

    it('links each row to its snippet\'s editor and says where it is output and what it holds', () => {
        drawSnippets(snippetsData());
        const faq = rowOf('FAQ');

        assert.equal(
            within(faq).getByRole('link').getAttribute('href'),
            `${SNIPPETS_ROUTES['hryvinskyi_richsnippets/snippets/edit'] ?? ''}?id=8`,
        );
        assert.ok(within(faq).getByText(/^snippets\.row\.where CMS pages CMS page is one of FAQ, Shipping & returns .*· snippets\.row\.someViews English/));
        assert.ok(within(faq).getByText(/^snippets\.row\.properties\.other 10 .* · snippets\.row\.changed 9 Nov admin/));
        assert.ok(within(faq).getByText('FAQPage'));
        assert.ok(within(faq).getByText('Google no longer shows FAQ results'));
    });

    it('filters by a chip and counts each from the server, the block chip last', () => {
        drawSnippets(snippetsData());

        assert.equal(chip(/^snippets\.chips\.all/).getAttribute('aria-pressed'), 'true');
        assert.ok(chip(/^Every other page/));
        fireEvent.click(chip(/^Product pages/));

        assert.equal(chip(/^Product pages/).getAttribute('aria-pressed'), 'true');
        assert.deepEqual(rowNames(), ['Product', 'Product (imported)']);
        fireEvent.click(chip(/^snippets\.chips\.blocks/));
        assert.deepEqual(rowNames(), ['Return policy']);
    });

    it('says what a chip leaves out and shows everything again', () => {
        drawSnippets(snippetsData());

        fireEvent.click(chip(/^Every other page/));
        assert.ok(screen.getByText(/^snippets\.noMatch\.kind every other page/));
        fireEvent.click(screen.getByRole('button', { name: /snippets\.noMatch\.reset/ }));

        assert.equal(rowNames().length, 6);
    });

    it('posts the state a switch is turned to, shows it at once, and keeps the server\'s answer', async () => {
        const { script } = drawSnippets(snippetsData());
        const toggle = within(rowOf('Product list')).getByRole('switch');
        assert.equal(toggle.getAttribute('aria-checked'), 'false');

        fireEvent.click(toggle);

        assert.deepEqual(script.callsTo('setActive'), [[6, true, null]]);
        assert.equal(toggle.getAttribute('aria-checked'), 'true');
        script.answer('setActive', writeAnswer(listChanging('product-list', { active: true })));
        await waitFor(() => assert.equal(rowOf('Product list').querySelector('.rs-snippets__row')?.getAttribute('data-muted'), 'false'));
        assert.equal(within(rowOf('Product list')).getByRole('switch').getAttribute('aria-checked'), 'true');
    });

    it('puts a switch back and says so when its request fails', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(within(rowOf('Organization')).getByRole('switch'));

        assert.deepEqual(script.callsTo('setActive'), [[1, false, null]]);
        script.refuse('setActive', new RequestError('server'));

        await waitFor(() => assert.ok(screen.getByRole('alert')));
        assert.equal(within(rowOf('Organization')).getByRole('switch').getAttribute('aria-checked'), 'true');
    });

    it('says why the server refused a switch', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(within(rowOf('Product list')).getByRole('switch'));

        script.answer('setActive', writeAnswer(contractList(), { reason: 'template_invalid', includers: [] }));

        await waitFor(() => assert.ok(screen.getByText(/^snippets\.refused\.template_invalid/)));
        assert.equal(within(rowOf('Product list')).getByRole('switch').getAttribute('aria-checked'), 'false');
    });

    it('says a snippet left with no store view needs one before it can be switched on', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(within(rowOf('Product list')).getByRole('switch'));

        script.answer('setActive', writeAnswer(contractList(), { reason: 'stores_missing', includers: [] }));

        await waitFor(() => assert.ok(screen.getByText(/^snippets\.refused\.stores_missing/)));
        assert.equal(screen.queryByText(/^snippets\.refused\.other/), null);
        assert.equal(within(rowOf('Product list')).getByRole('switch').getAttribute('aria-checked'), 'false');
    });
});

describe('l2 · one store view', () => {
    it('reads the store view picked, puts the chip back to every snippet, and counts its list', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(chip(/^snippets\.chips\.blocks/));

        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '2' } });

        assert.deepEqual(script.callsTo('list'), [[2]]);
        assert.ok(screen.getByText(/^snippets\.store\.oneLine Deutsch/));
        assert.ok(document.querySelector('.rs-snippets__skeleton[aria-busy="true"]'), 'grey rows while it loads');
        script.answer('list', listWith((row) => row['code'] !== 'faq'));

        await waitFor(() => assert.deepEqual(rowNames(), ['Organization', 'Product', 'Product list', 'Return policy', 'Product (imported)']));
        assert.equal(chip(/^snippets\.chips\.all/).getAttribute('aria-pressed'), 'true');
        assert.equal(chip(/^CMS pages/).textContent, 'CMS pages0');
    });

    it('says when a store view outputs no snippet, and goes back to every store view', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '3' } });
        script.answer('list', emptyList());

        await waitFor(() => assert.ok(screen.getByText(/^snippets\.noMatch\.store Trade/)));
        assert.ok(screen.getByRole('combobox', { name: /page\.store\.label/ }), 'the store bar stays, to go back');
        fireEvent.click(screen.getByRole('button', { name: /snippets\.noMatch\.reset/ }));

        assert.deepEqual(script.callsTo('list'), [[3], [null]]);
    });
});

describe('l3 · snippets that need attention', () => {
    it('counts them on a warning chip, edges their rows and shows their flags', () => {
        drawSnippets(snippetsData());

        fireEvent.click(chip(/^snippets\.chips\.attention/));

        assert.deepEqual(rowNames(), ['Product', 'Product (imported)']);
        assert.equal(rowOf('Product').querySelector('.rs-snippets__row')?.getAttribute('data-edge'), 'bad');
        assert.equal(rowOf('Product (imported)').querySelector('.rs-snippets__row')?.getAttribute('data-edge'), 'warn');
        assert.ok(within(rowOf('Product')).getByText('No offer: Google needs one'));
        assert.ok(within(rowOf('Product (imported)')).getByText('Second Product on the same pages'));
    });

    it('leaves the chip out while nothing needs attention', () => {
        drawSnippets(snippetsData(listWith((row) => row['code'] === 'organization')));

        assert.ok(screen.queryByRole('button', { name: /snippets\.chips\.attention/ }) === null);
    });
});

describe('l4 · the row menu', () => {
    const openMenu = (name: string): HTMLElement => {
        fireEvent.click(within(rowOf(name)).getByRole('button', { name: new RegExp(`^snippets\\.menu\\.more ${name.replace(/[()]/g, '\\$&')}`) }));
        return screen.getByRole('menu');
    };

    it('offers Duplicate, Export and Delete, and marks its row', () => {
        drawSnippets(snippetsData());
        const menu = openMenu('Product list');

        assert.deepEqual(within(menu).getAllByRole('menuitem').map((item) => item.textContent), [
            'snippets.menu.duplicate %1 %2 %3',
            'snippets.menu.export %1 %2 %3',
            'snippets.menu.delete %1 %2 %3',
        ]);
        assert.equal(rowOf('Product list').querySelector('.rs-snippets__row')?.getAttribute('data-marked'), 'true');
    });

    it('copies with a new key for every request and says the copy was added', async () => {
        const { script } = drawSnippets(snippetsData());

        fireEvent.click(within(openMenu('Product list')).getByRole('menuitem', { name: /duplicate/ }));
        fireEvent.click(within(openMenu('Product list')).getByRole('menuitem', { name: /duplicate/ }));

        assert.deepEqual(script.callsTo('duplicate'), [[6, 'key-1', null], [6, 'key-2', null]]);
        script.answer('duplicate', writeAnswer());
        await waitFor(() => assert.ok(screen.getByText(/^snippets\.toast\.duplicated Product list/)));
        fireEvent.click(screen.getByRole('button', { name: 'kit.strip.dismiss' }));
        assert.ok(screen.queryByText(/^snippets\.toast\.duplicated/) === null);
    });

    it('asks in the row before deleting, keeps the snippet when told to, and deletes it when confirmed', async () => {
        const { script } = drawSnippets(snippetsData());

        fireEvent.click(within(openMenu('Product list')).getByRole('menuitem', { name: /delete/ }));
        const question = within(rowOf('Product list')).getByRole('alert');
        assert.ok(within(question).getByText(/^snippets\.delete\.askOff Product list/));
        fireEvent.click(within(question).getByRole('button', { name: /snippets\.delete\.keep/ }));
        assert.ok(within(rowOf('Product list')).queryByRole('alert') === null);

        fireEvent.click(within(openMenu('Product list')).getByRole('menuitem', { name: /delete/ }));
        fireEvent.click(within(rowOf('Product list')).getByRole('button', { name: /snippets\.delete\.confirm/ }));
        assert.deepEqual(script.callsTo('remove'), [[6, null]]);
        script.answer('remove', writeAnswer(listWith((row) => row['code'] !== 'product-list')));

        await waitFor(() => assert.ok(screen.getByText(/^snippets\.toast\.deleted Product list/)));
        assert.equal(rowNames().includes('Product list'), false);
    });

    it('asks more firmly before deleting a snippet that is on', () => {
        drawSnippets(snippetsData());

        fireEvent.click(within(openMenu('Organization')).getByRole('menuitem', { name: /delete/ }));

        assert.ok(within(rowOf('Organization')).getByText(/^snippets\.delete\.askOn Organization/));
    });

    it('downloads one snippet\'s export and says under which name it was saved', async () => {
        const { script } = drawSnippets(snippetsData());

        fireEvent.click(within(openMenu('FAQ')).getByRole('menuitem', { name: /export/ }));

        assert.deepEqual(script.callsTo('one'), [[8]]);
        script.answer('one', { fileName: 'faq.json', count: 1 });
        await waitFor(() => assert.ok(screen.getByText(/^snippets\.toast\.exported FAQ faq\.json/)));
    });

    it('says when a copy could not be made', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(within(openMenu('FAQ')).getByRole('menuitem', { name: /duplicate/ }));

        script.refuse('duplicate', new RequestError('server'));

        await waitFor(() => assert.ok(screen.getByRole('alert')));
        assert.deepEqual(script.errors, ['The snippet duplicate failed.']);
    });

    it('says when a copy is refused, and logs nothing for a refusal the server answered', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(within(openMenu('FAQ')).getByRole('menuitem', { name: /duplicate/ }));

        script.refuse('duplicate', new RequestError('rejected', 'unknown_snippet'));

        await waitFor(() => assert.ok(screen.getByRole('alert')));
        assert.deepEqual(script.errors, []);
    });
});

describe('l5 · a building block in use', () => {
    it('says it cannot be deleted while snippets use it, naming them, with no Delete button', () => {
        drawSnippets(snippetsData());

        fireEvent.click(within(rowOf('Return policy')).getByRole('button', { name: /snippets\.menu\.more Return policy/ }));
        fireEvent.click(within(screen.getByRole('menu')).getByRole('menuitem', { name: /delete/ }));

        const notice = within(rowOf('Return policy')).getByRole('alert');
        assert.ok(within(notice).getByText(/^snippets\.delete\.inUse\.other 2 Return policy Organization and Product/));
        assert.ok(within(notice).queryByRole('button', { name: /snippets\.delete\.confirm/ }) === null);
        fireEvent.click(within(notice).getByRole('button', { name: /snippets\.delete\.close/ }));
        assert.ok(within(rowOf('Return policy')).queryByRole('alert') === null);
    });

    it('turns the question into the users the server names when it refuses the delete', async () => {
        const { script } = drawSnippets(snippetsData(listChanging('return-policy', { usedIn: [] })));
        fireEvent.click(within(rowOf('Return policy')).getByRole('button', { name: /snippets\.menu\.more Return policy/ }));
        fireEvent.click(within(screen.getByRole('menu')).getByRole('menuitem', { name: /delete/ }));
        fireEvent.click(within(rowOf('Return policy')).getByRole('button', { name: /snippets\.delete\.confirm/ }));

        script.answer('remove', writeAnswer(contractList(), { reason: 'in_use', includers: [{ code: 'faq', name: 'FAQ' }] }));

        await waitFor(() => assert.ok(within(rowOf('Return policy')).getByText(/^snippets\.delete\.inUse\.one 1 Return policy FAQ/)));
    });
});

describe('l6 · import', () => {
    function chooseFile(name: string, text: string): void {
        const input = screen.getByLabelText(/snippets\.import\.file\.label/);
        Object.defineProperty(input, 'files', { configurable: true, value: [new File([text], name, { type: 'application/json' })] });
        fireEvent.change(input);
    }

    it('previews the file chosen, offers a choice per snippet, and imports as chosen with its key', async () => {
        const { script } = drawSnippets(snippetsData());

        fireEvent.click(screen.getByRole('button', { name: /snippets\.move\.import/ }));
        const drawer = screen.getByRole('dialog', { name: /snippets\.import\.title/ });
        assert.ok(within(drawer).getByText(/^snippets\.import\.file\.none/));
        chooseFile('snippets.json', '{"hryvinskyi_rich_snippets":{}}');

        await waitFor(() => assert.deepEqual(script.callsTo('previewImport'), [['{"hryvinskyi_rich_snippets":{}}']]));
        script.answer('previewImport', importPreview());
        await waitFor(() => assert.ok(within(drawer).getByText('snippets.json')));
        assert.ok(within(drawer).getByText(/^snippets\.import\.file\.count\.other 4 .*· snippets\.import\.file\.exportedFrom 13 November 2026 shop\.example/));
        const product = within(drawer).getByRole('group', { name: 'Product' });
        assert.ok(within(drawer).getByText(/^snippets\.import\.status\.changed 12 November 13 November/));
        assert.ok(within(drawer).getByText(/^snippets\.import\.problem\.store_unknown trade_de/));
        const productEntry = product.closest('li');
        assert.ok(productEntry instanceof HTMLElement);
        assert.ok(within(productEntry).getByText(/^snippets\.import\.effect\.replaceOn/), 'the Product here is on');
        assert.ok(within(drawer).getByRole('button', { name: /^snippets\.import\.runCount\.other 3/ }));

        fireEvent.click(within(product).getByRole('button', { name: /snippets\.import\.choice\.skip/ }));
        assert.ok(within(productEntry).getByText(/^snippets\.import\.effect\.skipExisting/));
        fireEvent.click(within(drawer).getByRole('button', { name: /^snippets\.import\.runCount\.other 2/ }));

        assert.deepEqual(script.callsTo('commitImport'), [[
            '{"hryvinskyi_rich_snippets":{}}',
            { breadcrumbs: 'add', product: 'skip', organization: 'skip', 'return-policy': 'replace' },
            'key-2',
            null,
        ]]);
        script.answer('commitImport', {
            ...contractList(),
            imported: [
                { id: 12, code: 'breadcrumbs', name: 'Breadcrumbs', choice: 'add' },
                { id: 9, code: 'return-policy', name: 'Return policy', choice: 'replace' },
            ],
            count: 2,
        });
        await waitFor(() => assert.ok(screen.queryByRole('dialog') === null));
        assert.ok(screen.getByText(/^snippets\.toast\.imported\.other 2 snippets\.json/));
    });

    it('does nothing while the choices add no snippet', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(screen.getByRole('button', { name: /snippets\.move\.import/ }));
        chooseFile('snippets.json', '{}');
        await waitFor(() => assert.equal(script.callsTo('previewImport').length, 1));
        const preview = importPreview();
        script.answer('previewImport', { ...preview, snippets: Array.isArray(preview['snippets']) ? preview['snippets'].slice(2, 3) : [] });

        const run = await screen.findByRole('button', { name: /^snippets\.import\.run\b/ });

        assert.equal(run.hasAttribute('disabled'), true);
    });

    it('l7 · says a file is not an export, keeps its name and size, and changes nothing', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.click(screen.getByRole('button', { name: /snippets\.move\.import/ }));
        chooseFile('catalog-export.json', 'x'.repeat(48 * 1024));
        await waitFor(() => assert.equal(script.callsTo('previewImport').length, 1));

        script.refuse('previewImport', new RequestError('rejected', 'not_an_export'));

        const drawer = screen.getByRole('dialog');
        await waitFor(() => assert.ok(within(drawer).getByText(/^snippets\.import\.refused\.not_an_export/)));
        assert.ok(within(drawer).getByText('catalog-export.json'));
        assert.ok(within(drawer).getByText(/^snippets\.import\.file\.kilobytes 48/));
        assert.equal(drawer.querySelector('.rs-import__file')?.getAttribute('data-bad'), 'true');
        assert.equal(script.callsTo('commitImport').length, 0);
        assert.deepEqual(script.errors, [], 'a refusal the server answered is worded, not logged');
    });
});

describe('l8 · no snippets yet', () => {
    it('invites to the library and a blank snippet, with no chips, no store bar and nothing to export', () => {
        drawSnippets(snippetsData(emptyList()));

        assert.ok(screen.getByText(/^snippets\.empty\.title/));
        assert.equal(
            screen.getByRole('link', { name: /snippets\.empty\.library/ }).getAttribute('href'),
            SNIPPETS_ROUTES['hryvinskyi_richsnippets/library/index'],
        );
        assert.ok(screen.getByRole('link', { name: /snippets\.empty\.blank/ }));
        assert.ok(screen.queryByRole('group', { name: /snippets\.chips\.label/ }) === null);
        assert.ok(screen.queryByRole('combobox') === null);
        assert.equal(screen.getByRole('button', { name: /snippets\.move\.export/ }).hasAttribute('disabled'), true);
        assert.equal(screen.getByRole('button', { name: /snippets\.move\.import/ }).hasAttribute('disabled'), false);
    });
});

describe('l9 and l10 · loading, and a list that could not be read', () => {
    it('shows grey rows while the list loads, the failure with Try again, then the list read again', async () => {
        const { script } = drawSnippets(snippetsData());
        fireEvent.change(screen.getByRole('combobox', { name: /page\.store\.label/ }), { target: { value: '1' } });
        assert.ok(screen.getByText(/^page\.loading/));

        script.refuse('list', new RequestError('server'));

        await waitFor(() => assert.ok(screen.getByText(/^snippets\.failed\.text/)));
        assert.ok(screen.queryByRole('group', { name: /snippets\.chips\.label/ }) === null);
        fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
        assert.deepEqual(script.callsTo('list'), [[1], [1]]);
        script.answer('list', contractList());
        await waitFor(() => assert.equal(rowNames().length, 6));
        assert.deepEqual(script.errors, ['The snippet list could not be read.']);
    });
});

describe('an admin who may only look', () => {
    it('gets no switch, no Add, no row menu and no moving between shops; the editor links and the block counts stay', () => {
        const { script } = drawSnippets(snippetsData(), { canChange: false });

        assert.equal(screen.queryAllByRole('switch').length, 0);
        assert.ok(screen.queryByRole('link', { name: /snippets\.header\.library/ }) === null);
        assert.ok(screen.queryByRole('link', { name: /snippets\.header\.blank/ }) === null);
        assert.equal(screen.queryAllByRole('button', { name: /snippets\.menu\.more/ }).length, 0);
        assert.ok(screen.queryByRole('button', { name: /snippets\.move\.import/ }) === null);
        assert.ok(screen.queryByRole('button', { name: /snippets\.move\.export/ }) === null);
        assert.ok(within(rowOf('FAQ')).getByRole('link'));
        assert.ok(within(rowOf('Return policy')).getByText(/^snippets\.blocks\.usedIn\.other 2/));
        assert.deepEqual(script.calls, []);
    });
});

describe('moving snippets between shops', () => {
    it('downloads every snippet and says how many went into which file', async () => {
        const { script } = drawSnippets(snippetsData());

        fireEvent.click(screen.getByRole('button', { name: /snippets\.move\.export/ }));

        assert.deepEqual(script.callsTo('all'), [[]]);
        script.answer('all', { fileName: 'rich-snippets-shop.json', count: 6 });
        await waitFor(() => assert.ok(screen.getByText(/^snippets\.toast\.exportedAll\.other 6 rich-snippets-shop\.json/)));
    });
});
