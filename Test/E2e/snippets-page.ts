import { expect } from '@hryvinskyi/magento2-e2e';
import type { Locator } from '@hryvinskyi/magento2-e2e';
import type { RichSnippetsAdmin } from './rich-snippets-admin';

/** A row of the Snippets list. */
export interface SnippetRowInfo {
    readonly name: string;
    /** A building block has no page of its own and no switch. */
    readonly isBlock: boolean;
}

/** The Snippets page: every snippet of the shop with its switch and row menu. */
export class SnippetsPage {
    readonly pages: RichSnippetsAdmin;

    constructor(pages: RichSnippetsAdmin) {
        this.pages = pages;
    }

    async open(): Promise<void> {
        await this.pages.goTo('Snippets');
        await expect(this.pages.page.locator('.rs-snippets')).toBeVisible();
    }

    /** The list items of the snippets, one per snippet. */
    private get items(): Locator {
        return this.pages.page.locator('.rs-snippets__item');
    }

    /** The list item of the snippet called `name`. */
    row(name: string): Locator {
        return this.items.filter({ has: this.pages.page.locator('.rs-snippets__name').getByText(name, { exact: true }) });
    }

    /** The switch of a snippet that has a page of its own. */
    switchOf(name: string): Locator {
        return this.row(name).getByRole('switch', { name, exact: true });
    }

    /** The names of every snippet of the list, and whether each is a building block. */
    async rows(): Promise<readonly SnippetRowInfo[]> {
        await expect(this.items.first(), 'the list has drawn its first snippet').toBeVisible();
        const infos: SnippetRowInfo[] = [];

        for (const item of await this.items.all()) {
            const name = (await item.locator('.rs-snippets__name').innerText()).trim();

            infos.push({ name, isBlock: (await item.getByRole('switch').count()) === 0 });
        }

        return infos;
    }

    /** The names of the snippets the shop holds; none when the list says nothing is output instead of drawing rows. */
    async names(): Promise<readonly string[]> {
        if ((await this.items.count()) === 0 && (await this.pages.page.getByText('Nothing is output.').count()) > 0) {
            return [];
        }

        return (await this.rows()).map(row => row.name);
    }

    /** Switches a snippet on and waits until the switch says so. */
    async switchOn(name: string): Promise<void> {
        const control = this.switchOf(name);

        await expect(control).toHaveAttribute('aria-checked', 'false');
        await control.click();
        await expect(control).toHaveAttribute('aria-checked', 'true');
    }

    /** Opens the editor of a snippet through its row. */
    async openEditor(name: string): Promise<void> {
        await this.pages.admin.follow(this.row(name).locator('a.rs-snippets__open'));
        await this.pages.admin.settled();
        await expect(this.pages.page.locator('.rs-editor')).toBeVisible();
    }

    /** Deletes a snippet through its row menu, confirming it, and waits until it is gone. */
    async delete(name: string): Promise<void> {
        const row = this.row(name);

        await row.getByRole('button', { name: `More for ${name}` }).click();
        await this.pages.page.getByRole('menuitem', { name: 'Delete' }).click();
        await row.getByRole('alert').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(row).toHaveCount(0);
    }
}
