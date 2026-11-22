/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The browser's file saving, as a page's composition root hands it to a download: the text becomes a file of the
 * given type, offered through a hidden link that is clicked and taken away, its address released right after.
 */
import type { SaveFile } from './file-download.ts';

/** Saves files of one type (`application/json`, `text/csv`) in the document given. */
export const browserSave = (document: Document, type: string): SaveFile => (fileName: string, text: string): void => {
    const address = URL.createObjectURL(new Blob([text], { type }));
    const anchor = document.createElement('a');
    anchor.href = address;
    anchor.download = fileName;
    anchor.hidden = true;
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    setTimeout(() => URL.revokeObjectURL(address), 0);
};
