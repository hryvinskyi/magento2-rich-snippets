import { ADMIN_TAG, evidence, expect } from '@hryvinskyi/magento2-e2e';
import { test } from './rich-snippets-test';

// A trace would keep the admin's session cookie.
test.use({ trace: 'off' });

/** The block the library's Product snippet writes: a JSON-LD script that names the snippet. */
const PRODUCT_BLOCK = 'script[type="application/ld+json"][data-snippet="product"]';

const PRODUCT_SNIPPET = 'Product';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

test(
    'the recommended set is added, the Product snippet previews, is switched on and is output on a product page',
    { tag: ADMIN_TAG },
    async ({ pages, library, snippets, editor, testPage, madeSnippets, storefront }) => {
        // Adding five snippets and deleting them again is many admin page loads.
        test.slow();

        let productName = '';
        let productPath = '';

        await test.step('the shop holds no Product snippet yet, so the recommended set is on offer', async () => {
            await pages.openFromMenu();
            await snippets.open();
            const existing = await snippets.names();

            madeSnippets.keepExisting(existing);
            expect(existing, 'the test needs a shop that has no Product snippet; delete it first').not.toContain(PRODUCT_SNIPPET);

            await library.open();
            await expect(library.recommendedOffer).toBeVisible();
        });

        await test.step('adding the recommended set adds the snippets, switched off', async () => {
            await library.addRecommendedSet();
            await snippets.open();

            await expect(snippets.row(PRODUCT_SNIPPET)).toBeVisible();
            await expect(snippets.switchOf(PRODUCT_SNIPPET)).toHaveAttribute('aria-checked', 'false');
        });

        await test.step('the editor previews the Product snippet as a script block', async () => {
            await snippets.openEditor(PRODUCT_SNIPPET);

            const previewed = await editor.previewOfAPageWithOutput();

            expect(previewed.output, 'the preview prints the Product type').toContain('Product');
            expect(previewed.path, 'the preview names the page it was made for').toMatch(/^\/\S+$/);
            productPath = previewed.path;
            await evidence('The Product snippet previewed', editor.preview);
        });

        await test.step('switching the snippet on is kept', async () => {
            await snippets.open();
            await snippets.switchOn(PRODUCT_SNIPPET);

            await snippets.open();
            await expect(snippets.switchOf(PRODUCT_SNIPPET), 'the switch stays on after a reload').toHaveAttribute('aria-checked', 'true');
        });

        await test.step('the product page carries the Product block with valid JSON', async () => {
            // A query nobody asked for before makes the full-page cache build the page now, so the block read is
            // the one the switch produced and not a copy made before it.
            await storefront.open(`${productPath}?rs-e2e=${String(Date.now())}`);

            const block = storefront.page.locator(PRODUCT_BLOCK);

            await expect(block, 'one Product block is output').toHaveCount(1);

            const parsed: unknown = JSON.parse((await block.textContent()) ?? '');

            expect(isRecord(parsed), 'the block holds a JSON object').toBe(true);
            expect(isRecord(parsed) ? parsed['@type'] : undefined).toBe('Product');

            productName = (await storefront.page.locator('h1.page-title').innerText()).trim();
            expect(productName).not.toBe('');
        });

        await test.step('Test › One page reads the same product as meeting the merchant listing', async () => {
            await pages.openFromMenu();
            await testPage.openOnePage();
            await testPage.pickProduct(productName);

            await expect(testPage.blocks.first(), 'the tested page carries a block').toBeVisible();
            await expect(testPage.verdict('Merchant listing'), 'the page meets what Google asks of a merchant listing').toHaveText('Met');
            await evidence('The product page meets the merchant listing', testPage.result('Merchant listing'));
        });
    },
);
