/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Changes of the draft that keep its parts in step: the template is the one source of the properties and the root
 * type, so a new template carries its type into the draft, and a new type is printed into the template's root. A
 * template the builder cannot draw keeps the type the admin typed until it can.
 *
 * A draft is unsaved when anything the admin can change differs from the snippet as last saved.
 */
import type { Draft } from './model.ts';
import { parseTemplate, printTree } from './template-projection.ts';
import { firstType, type Tree } from './template-tree.ts';
import { withRootType } from './tree-edits.ts';

/** The draft with a new template, and the type it names when the builder can read one. */
export function withTemplate(draft: Draft, template: string): Draft {
    const projection = parseTemplate(template);
    return projection.kind === 'tree'
        ? { ...draft, template, type: firstType(projection.tree.types) }
        : { ...draft, template };
}

/** The draft with a new tree printed as its template. */
export const withTree = (draft: Draft, tree: Tree): Draft => withTemplate(draft, printTree(tree));

/** The draft with a new root type, printed into the template when the builder can read it. */
export function withType(draft: Draft, type: string): Draft {
    const projection = parseTemplate(draft.template);
    if (projection.kind !== 'tree') {
        return { ...draft, type };
    }
    return { ...withTree(draft, withRootType(projection.tree, type)), type };
}

const sameList = <T>(left: readonly T[], right: readonly T[]): boolean =>
    left.length === right.length && left.every((item, index) => item === right[index]);

/** Whether the draft differs from the snippet as saved in anything the admin can change. */
export const isUnsaved = (saved: Draft, draft: Draft): boolean =>
    saved.name !== draft.name
    || saved.type !== draft.type
    || saved.template !== draft.template
    || saved.active !== draft.active
    || saved.conditions !== draft.conditions
    || !sameList(saved.pageKinds, draft.pageKinds)
    || !sameList(saved.storeIds, draft.storeIds);
