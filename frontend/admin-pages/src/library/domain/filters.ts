/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Which templates the card shows: the search matches a template's name, type, description and Google line, whatever
 * their case; the chips narrow to one group, and count what the search lets through, so their counts follow the
 * search. With every group shown, each group is headed by its label; a group the search and the chip leave empty is
 * not shown, and when none is left the card says nothing matches. Pure: the words are the server's or phrases.
 */
import { phrase, type Phrase } from '@hryvinskyi/admin-ui';
import type { Library, LibraryGroup, LibraryTemplate } from './model.ts';

/** The group chip pressed; null for every group. */
export type GroupFilter = string | null;

/** One chip: the group it narrows to, its words (a phrase of the page's, or a group's label) and its count. */
export interface Chip {
    readonly key: string;
    readonly group: GroupFilter;
    readonly label: Phrase | string;
    readonly count: number;
}

/** One group of the card: its label as a heading (only while every group is shown), and its templates. */
export interface Section {
    readonly group: LibraryGroup;
    readonly heading: boolean;
    readonly templates: readonly LibraryTemplate[];
}

const ALL_KEY = 'all';

/** The search as it is compared: trimmed, in lower case; empty matches every template. */
export const searchTerm = (query: string): string => query.trim().toLocaleLowerCase();

/** Whether a template's name, type, description or Google line holds the search. */
export function matchesSearch(template: LibraryTemplate, query: string): boolean {
    const term = searchTerm(query);
    if (term === '') {
        return true;
    }
    return [template.name, template.type, template.description, template.google.short]
        .some((text) => text.toLocaleLowerCase().includes(term));
}

/** The templates the search lets through, in the library's order. */
export const searched = (library: Library, query: string): readonly LibraryTemplate[] =>
    library.templates.filter((template) => matchesSearch(template, query));

/** The chips in the library's order: every group, then each group, counted over what the search lets through. */
export function chipsOf(library: Library, query: string): readonly Chip[] {
    const found = searched(library, query);
    return [
        { key: ALL_KEY, group: null, label: phrase('library.chips.all'), count: found.length },
        ...library.groups.map((group) => ({
            key: `group:${group.code}`,
            group: group.code,
            label: group.label,
            count: found.filter((template) => template.group === group.code).length,
        })),
    ];
}

/** The key of the chip a filter presses. */
export const chipKey = (group: GroupFilter): string => (group === null ? ALL_KEY : `group:${group}`);

/** The groups the card shows, each with the templates the search and the chip let through; empty when none match. */
export function sectionsOf(library: Library, query: string, group: GroupFilter): readonly Section[] {
    const found = searched(library, query);
    return library.groups
        .filter((candidate) => group === null || candidate.code === group)
        .map((candidate) => ({
            group: candidate,
            heading: group === null,
            templates: found.filter((template) => template.group === candidate.code),
        }))
        .filter((section) => section.templates.length > 0);
}
