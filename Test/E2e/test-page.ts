import { expect } from '@hryvinskyi/magento2-e2e';
import type { Locator } from '@hryvinskyi/magento2-e2e';
import type { RichSnippetsAdmin } from './rich-snippets-admin';

/** The Test page in its One page mode: what a page of the storefront outputs, and what Google asks of it. */
export class TestPage {
    readonly pages: RichSnippetsAdmin;

    constructor(pages: RichSnippetsAdmin) {
        this.pages = pages;
    }

    /** Opens the page in its One page mode. */
    async openOnePage(): Promise<void> {
        await this.pages.goTo('Test');
        await this.pages.page.getByRole('group', { name: 'Test' }).getByRole('button', { name: 'One page' }).click();
        await expect(this.picker).toBeVisible();
    }

    private get picker(): Locator {
        return this.pages.page.getByRole('combobox', { name: 'Page' });
    }

    /** Picks the product called `name` from the page picker; the test of the page starts at once. */
    async pickProduct(name: string): Promise<void> {
        await this.picker.click();
        await this.picker.fill(name);
        await this.pages.page.getByRole('listbox', { name: 'Pages' })
            .getByRole('option', { name: new RegExp(`^Product · ${escaped(name)}`) })
            .first()
            .click();
    }

    /** The script blocks the tested page carries, each a row of the output card. */
    get blocks(): Locator {
        return this.pages.page.locator('.rs-test__block');
    }

    /** The side panel's entry for a kind of result Google shows, such as the merchant listing. */
    result(label: string): Locator {
        return this.pages.page.locator('.rs-test__feature').filter({ has: this.pages.page.locator('.rs-test__feature-label', { hasText: label }) });
    }

    /** What the side panel's entry says of the page: Met, Not met or Left out. */
    verdict(label: string): Locator {
        return this.result(label).locator('.hui-pill');
    }
}

const escaped = (text: string): string => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
