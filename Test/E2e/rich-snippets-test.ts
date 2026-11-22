import { test as base } from '@hryvinskyi/magento2-e2e';
import { EditorPage } from './editor-page';
import { LibraryPage } from './library-page';
import { RichSnippetsAdmin } from './rich-snippets-admin';
import { SnippetsPage } from './snippets-page';
import { TestPage } from './test-page';

/** The snippets a test adds, removed again through the Snippets page whether the test passed or not. */
export interface MadeSnippets {
    /** Notes the snippets the shop holds before the test adds any; every other snippet is the test's and is deleted. */
    keepExisting(names: readonly string[]): void;
}

interface RichSnippetsFixtures {
    pages: RichSnippetsAdmin;
    library: LibraryPage;
    snippets: SnippetsPage;
    editor: EditorPage;
    testPage: TestPage;
    madeSnippets: MadeSnippets;
}

/** The package's `test`, with the module's admin pages a test of this module works on. */
export const test = base.extend<RichSnippetsFixtures>({
    pages: async ({ admin }, use) => {
        await use(new RichSnippetsAdmin(admin));
    },
    library: async ({ pages }, use) => {
        await use(new LibraryPage(pages));
    },
    snippets: async ({ pages }, use) => {
        await use(new SnippetsPage(pages));
    },
    editor: async ({ pages }, use) => {
        await use(new EditorPage(pages));
    },
    testPage: async ({ pages }, use) => {
        await use(new TestPage(pages));
    },
    madeSnippets: async ({ pages, snippets }, use) => {
        const kept: { names: ReadonlySet<string> | null } = { names: null };

        await use({ keepExisting: names => void (kept.names = new Set(names)) });

        const existing = kept.names;

        if (existing === null) {
            return;
        }

        await pages.openFromMenu();
        await snippets.open();
        const made = (await snippets.rows()).filter(row => !existing.has(row.name));

        // A building block that snippets use cannot be deleted before they are, so the snippets go first.
        for (const row of [...made.filter(row => !row.isBlock), ...made.filter(row => row.isBlock)]) {
            await snippets.delete(row.name);
        }
    },
});
