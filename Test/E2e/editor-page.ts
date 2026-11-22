import { expect } from '@hryvinskyi/magento2-e2e';
import type { Locator } from '@hryvinskyi/magento2-e2e';
import type { RichSnippetsAdmin } from './rich-snippets-admin';

/** A sample page the preview was made for, with what the snippet prints for it. */
export interface PreviewedPage {
    /** The storefront path of the sample page, as the preview shows it. */
    readonly path: string;
    /** The JSON the preview prints. */
    readonly output: string;
}

/** The snippet editor, with its Preview panel. */
export class EditorPage {
    readonly pages: RichSnippetsAdmin;

    constructor(pages: RichSnippetsAdmin) {
        this.pages = pages;
    }

    /** The Preview panel beside the editor's cards. */
    get preview(): Locator {
        return this.pages.page.locator('section.rs-editor__preview');
    }

    /** The coloured JSON the preview prints. */
    private get output(): Locator {
        return this.preview.locator('.rs-editor__output');
    }

    /** The path of the sample page the preview is made for. */
    private get path(): Locator {
        return this.preview.locator('.rs-editor__preview-path');
    }

    /** The list of sample pages; the preview has none when the snippet has a single sample. */
    private get samples(): Locator {
        return this.preview.getByRole('combobox', { name: 'Preview with' });
    }

    /** Waits until the preview has made its output, or said why it has none, for the sample it shows. */
    private async settled(): Promise<void> {
        await expect(this.preview.getByRole('heading', { name: 'Preview' })).toBeVisible();
        await expect(this.preview).toHaveAttribute('aria-busy', 'false');
        await expect(this.preview.locator('.rs-editor__output, .rs-editor__nothing').first()).toBeVisible();
    }

    /**
     * The preview of the first sample page the snippet is output on. The list of samples is not narrowed by the
     * snippet's conditions, so its first page can be one the snippet skips (a configurable product, for the
     * Product snippet); the samples are walked until one prints a block.
     */
    async previewOfAPageWithOutput(): Promise<PreviewedPage> {
        await this.settled();

        const values: string[] = [];

        for (const option of (await this.samples.count()) === 0 ? [] : await this.samples.locator('option').all()) {
            values.push((await option.getAttribute('value')) ?? '');
        }

        let index = values.indexOf(values.length === 0 ? '' : await this.samples.inputValue());

        for (;;) {
            if ((await this.output.count()) > 0) {
                return { path: (await this.path.innerText()).trim(), output: (await this.output.innerText()).trim() };
            }

            const next = values[index + 1];

            if (next === undefined) {
                throw new Error(`None of the ${String(Math.max(values.length, 1))} sample pages the preview offers is one the snippet is output on.`);
            }

            const before = await this.path.innerText();

            await this.samples.selectOption(next);
            await expect(this.path).not.toHaveText(before);
            await this.settled();
            index += 1;
        }
    }
}
