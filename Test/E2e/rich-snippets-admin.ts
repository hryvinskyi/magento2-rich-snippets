import { expect } from '@hryvinskyi/magento2-e2e';
import type { Admin, Locator, Page } from '@hryvinskyi/magento2-e2e';

/** The pages of the module's navigation, by the name its tab carries. */
export type ModuleTab = 'Overview' | 'Snippets' | 'Library' | 'Test';

/** The module's admin pages, reached the way a person reaches them: the admin menu first, then the module's own tabs. */
export class RichSnippetsAdmin {
    readonly admin: Admin;

    constructor(admin: Admin) {
        this.admin = admin;
    }

    get page(): Page {
        return this.admin.page;
    }

    /** Opens the module's first page from Marketing › SEO › Rich Snippets. */
    async openFromMenu(): Promise<void> {
        await this.admin.openMenuItem('menu-hryvinskyi-richsnippets-rich-snippets');
        await this.admin.settled();
        await expect(this.tab('Overview')).toHaveAttribute('aria-current', 'page');
    }

    /** Follows a tab of the module's navigation and waits until the page it opens has drawn its tab as current. */
    async goTo(tab: ModuleTab): Promise<void> {
        if ((await this.tab(tab).count()) === 0) {
            await this.openFromMenu();
        }

        await this.admin.follow(this.tab(tab));
        await this.admin.settled();
        await expect(this.tab(tab)).toHaveAttribute('aria-current', 'page');
    }

    private tab(name: ModuleTab): Locator {
        return this.page.locator('nav.hui-modnav').getByRole('link', { name, exact: true });
    }
}
