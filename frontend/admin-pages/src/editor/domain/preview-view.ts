/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the editor's side column decides from what the server answered: the store view the editor reads in, the sample
 * page a preview is made for, the count on the Checks panel's head, the note under the output, and the output as the
 * panel prints and copies it.
 *
 * The editor reads everything — the sample pages, the field catalog with its examples, the expression builder's
 * options, the placement count, the preview — in one store view: the first one the snippet is output in, or the
 * default store view when it is output in every one.
 */
import type { CheckRow, PreviewResult, Sample } from './model.ts';

const EVERY_STORE = 0;

/**
 * The store view the editor reads in: the first one the snippet is set for that the shop still has (`known`); null for
 * the default store view.
 */
export const editorStore = (storeIds: readonly number[], known: readonly number[]): number | null =>
    storeIds.find((id) => id !== EVERY_STORE && known.includes(id)) ?? null;

/** The sample to preview: the one wanted when it is still among the samples, else the first; null for none. */
export function chosenSample(samples: readonly Sample[], wanted: string | null): Sample | null {
    return samples.find((sample) => sample.ref === wanted) ?? samples[0] ?? null;
}

/** The count on the Checks panel's head: the rows a requirement is missing in, else the warnings; null for none. */
export function checkCount(rows: readonly CheckRow[]): { readonly tone: 'bad' | 'warn'; readonly count: number } | null {
    const bad = rows.filter((row) => row.tone === 'bad').length;
    if (bad > 0) {
        return { tone: 'bad', count: bad };
    }
    const warn = rows.filter((row) => row.tone === 'warn').length;
    return warn > 0 ? { tone: 'warn', count: warn } : null;
}

/** What the note under the output says: the last valid version is shown, a block as inside its parent, or a page's block. */
export type OutputNote = 'lastValid' | 'block' | 'page';

export function outputNote(isBlock: boolean, codeInvalid: boolean): OutputNote {
    if (codeInvalid) {
        return 'lastValid';
    }
    return isBlock ? 'block' : 'page';
}

/** The output as the panel prints it and the Copy button copies it: two spaces per level; empty for none. */
export const outputText = (preview: PreviewResult | null): string =>
    preview === null || preview.output === null ? '' : JSON.stringify(preview.output, null, 2);
