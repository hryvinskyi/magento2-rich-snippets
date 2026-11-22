/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the message after a save says about where the new version is seen: a building block is seen in the snippets
 * that use it, a snippet switched on on its pages, and a snippet switched off nowhere yet.
 */
import type { Draft } from './model.ts';

export type SavedReach = 'block' | 'live' | 'off';

export function savedReach(draft: Draft): SavedReach {
    if (draft.isBlock) {
        return 'block';
    }
    return draft.active ? 'live' : 'off';
}
