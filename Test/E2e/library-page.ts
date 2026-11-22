import { expect } from '@hryvinskyi/magento2-e2e';
import type { Locator } from '@hryvinskyi/magento2-e2e';
import type { RichSnippetsAdmin } from './rich-snippets-admin';

/** The Library page: the templates the module ships, and the offer of the recommended set. */
export class LibraryPage {
    readonly pages: RichSnippetsAdmin;

    constructor(pages: RichSnippetsAdmin) {
        this.pages = pages;
    }

    /** The first-visit offer of the recommended set; it is drawn only while a snippet of the set is missing. */
    get recommendedOffer(): Locator {
        return this.pages.page.getByRole('region', { name: 'Start with the five most shops need' });
    }

    async open(): Promise<void> {
        await this.pages.goTo('Library');
        await expect(this.pages.page.getByRole('article').first()).toBeVisible();
    }

    /** Adds the recommended set and waits until the page says what it added. */
    async addRecommendedSet(): Promise<void> {
        await this.recommendedOffer.getByRole('button', { name: 'Add the recommended set' }).click();

        await expect(
            this.pages.page.getByRole('status').filter({ hasText: /were added, all switched off|was added, switched off/ }),
        ).toBeVisible();
        await expect(this.recommendedOffer, 'the offer goes once the set is added').toHaveCount(0);
    }
}
