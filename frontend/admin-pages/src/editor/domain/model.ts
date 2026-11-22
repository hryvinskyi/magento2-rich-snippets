/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The snippet editor's words for what it shows: the snippet being edited (the draft), the snippets that include it,
 * the kinds of page and their condition fields, the field catalog with its sample page, the snippets it may include,
 * the placement count, and the preview other parts of the editor hand it.
 *
 * The server decides whether a draft may be saved and what a page outputs; these shapes only carry what it said.
 */

/** Where a snippet stands in the library it was added from; null for one written in the shop. */
export interface SnippetOrigin {
    readonly code: string;
    readonly version: number;
}

/** A snippet as the editor holds it: what is saved, and what the admin changes until the next save. */
export interface Draft {
    /** Null until the snippet is first saved. */
    readonly id: number | null;
    readonly code: string;
    readonly name: string;
    /** The schema.org type the list shows, as the server read it from the template. */
    readonly type: string;
    /** The one source of the snippet's properties: the builder reads its tree from it and prints edits back. */
    readonly template: string;
    readonly isBlock: boolean;
    readonly active: boolean;
    readonly pageKinds: readonly string[];
    /** The condition tree as the expression builder writes it; empty for no condition. */
    readonly conditions: string;
    /** The store views it is output in; `0` alone for every store view. */
    readonly storeIds: readonly number[];
    readonly origin: SnippetOrigin | null;
    readonly updatedAt: string | null;
    readonly updatedBy: string;
}

/** A snippet that includes the one being edited, with the paths it includes it at. */
export interface UsedIn {
    readonly id: number | null;
    readonly code: string;
    readonly name: string;
    readonly paths: readonly string[];
}

/** A field a condition may test, for one kind of page. */
export interface ConditionField {
    readonly code: string;
    readonly label: string;
}

/** A kind of page a snippet can be output on, in the admin's words. */
export interface PageKind {
    readonly code: string;
    readonly label: string;
    /** The kind as a sentence names its pages ("product pages"). */
    readonly phrase: string;
    /** One page of the kind ("Product page"). */
    readonly page: string;
    readonly icon: string;
}

/** The values a property starts with when its kind of value changes. */
export interface ValueDefaults {
    readonly text: string;
    readonly code: string;
    readonly when: string;
    readonly list: { readonly each: string; readonly item: string };
}

/** The two sections of a snippet's editor. */
export type EditorSection = 'properties' | 'pages';

export interface EditorData {
    readonly snippet: Draft;
    readonly isNew: boolean;
    /** The section the editor opens on. */
    readonly section: EditorSection;
    readonly usedIn: readonly UsedIn[];
    /** The fields a condition may test, by kind of page. */
    readonly conditions: ReadonlyMap<string, readonly ConditionField[]>;
    readonly kinds: readonly PageKind[];
    readonly typeSuggestions: readonly string[];
    readonly defaults: ValueDefaults;
}

/** A page the editor shows values for. */
export interface Sample {
    /** `kind:id`, as the endpoints take it. */
    readonly ref: string;
    readonly kind: string;
    readonly id: number | null;
    readonly label: string;
    readonly url: string;
    readonly path: string;
}

export interface CatalogField {
    readonly path: string;
    readonly label: string;
    readonly attributeCode: string | null;
    readonly pageKinds: readonly string[];
    /** What the field holds on the sample page, as text; null when it is empty there or no page was given. */
    readonly example: string | null;
}

export interface FieldGroup {
    readonly code: string;
    readonly label: string;
    readonly fields: readonly CatalogField[];
}

/** A store collection a list may output one item per element of. */
export interface CatalogList {
    readonly path: string;
    readonly label: string;
    /** The name each element is read under (`image`). */
    readonly itemName: string;
}

/** A property schema.org and Google recommend for a type, with the field the module would fill it from. */
export interface Suggestion {
    readonly name: string;
    readonly required: boolean;
    readonly field: string | null;
}

export interface FieldCatalog {
    readonly groups: readonly FieldGroup[];
    readonly lists: readonly CatalogList[];
    /** The values Google accepts for a property, by property name. */
    readonly enums: ReadonlyMap<string, readonly string[]>;
    /** By type, then by the path of the object under the type (`""` for the type itself). */
    readonly suggestions: ReadonlyMap<string, ReadonlyMap<string, readonly Suggestion[]>>;
    /** By type, then by property name: the field a property of that name is taken from when the name is typed in. */
    readonly namedFields: ReadonlyMap<string, ReadonlyMap<string, string>>;
    readonly sample: Sample | null;
}

/** A snippet the edited one may include. */
export interface IncludeCandidate {
    readonly code: string;
    readonly name: string;
    readonly type: string;
    readonly isBlock: boolean;
    readonly description: string;
    /** It already includes the edited snippet, so including it would build a loop. */
    readonly loops: boolean;
}

/** What the placement asks for, as the count endpoint takes it. */
export interface Placement {
    readonly pageKinds: readonly string[];
    readonly conditions: string;
    readonly storeIds: readonly number[];
}

export interface KindCount {
    readonly matching: number;
    readonly total: number;
}

/** How many pages the placement matches, with the server's sentence about it. */
export interface PlacementCount {
    readonly kinds: ReadonlyMap<string, KindCount>;
    readonly matching: number;
    readonly total: number;
    readonly sentence: string;
    readonly count: string;
    readonly countLine: string;
    /** A note about conditions asked of pages they are not about; null for none. */
    readonly asked: string | null;
}

/** Something the server found wrong with a draft, with its sentence; a blocking one stops the save. */
export interface DraftProblem {
    /** The part of the draft it is about: `template`, `type`, `pageKinds`, … */
    readonly part: string;
    readonly code: string;
    readonly message: string;
    readonly blocking: boolean;
}

/** What the draft gave on the sample page: an output, or why there is none. */
export type PreviewState = 'output' | 'no_type' | 'invalid' | 'conditions_not_met' | 'empty';

/** How a check row reads: a requirement missing, a warning, a note, or met. */
export type CheckTone = 'bad' | 'warn' | 'note' | 'ok';

/**
 * What a check row offers to do: put a property back into the template (`path`, with the value the template it came
 * from gives it and the names of the properties it stands before there), or — with no path — open the Pages section.
 */
export interface CheckAction {
    readonly label: string;
    readonly icon: string;
    /** The property's path (`offers`, `offers.shippingDetails`); null for the Pages section. */
    readonly path: string | null;
    /** The property's value as a template part; null when no template the snippet may come from has one. */
    readonly value: string | null;
    /** The properties it is written before in that template, in their order. */
    readonly before: readonly string[];
}

export interface CheckRow {
    readonly tone: CheckTone;
    readonly title: string;
    readonly text: string;
    readonly action: CheckAction | null;
}

/**
 * The page a preview was made for and what the draft output there: the output object (null when nothing was
 * output), why nothing was (`message`, with its title and text), and the rows of the Checks panel. The builder reads
 * the output for its row markers and the drawer's result.
 */
export interface PreviewResult {
    readonly sample: Sample;
    readonly state: PreviewState;
    readonly output: Readonly<Record<string, unknown>> | null;
    readonly message: { readonly title: string; readonly text: string } | null;
    readonly checks: readonly CheckRow[];
}
