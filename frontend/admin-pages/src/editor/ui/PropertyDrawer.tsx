/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The property drawer: the properties Google recommends for the object, the property's name, the seven kinds of
 * value as tiles with the editor of the kind picked, the field it is output only when, and what it gives on the
 * preview's page.
 *
 * Changes stay in the drawer until Done (or Add property), so a value half entered — a field not picked yet, a
 * snippet not chosen — never reaches the template; Cancel, Escape and the scrim leave the template as it was.
 *
 * A suggestion picked, or a name typed in, brings the field the type takes a property of that name from (`material`
 * from `product.material`), unless a field was already picked by hand.
 */
import type { JSX } from 'preact';
import { useState } from 'preact/hooks';
import { Button, Drawer, FieldRow, RadioCards, SelectInput, Switch, TextInput, usePage } from '@hryvinskyi/admin-ui';
import { withTree } from '../domain/draft.ts';
import { conditionGroups, isComplete, isUsableName, namedValue, suggestionsFor, valueOfKind } from '../domain/drawer-view.ts';
import type { FieldCatalog, IncludeCandidate, PreviewResult } from '../domain/model.ts';
import { fieldName, rootType, slotOutput } from '../domain/row-view.ts';
import { childrenOf, firstType, isProperty, VALUE_KINDS, type Property, type Slot, type Tree, type Value, type ValueKind } from '../domain/template-tree.ts';
import { appendSlot, childrenAt, removeSlot, replaceSlot, slotAt, type SlotPath } from '../domain/tree-edits.ts';
import { useEditorEnv } from './editor-env.tsx';
import { KIND_ICONS, KindEditor } from './kind-editors.tsx';

/** The row the drawer edits, or the row a new property or item is added under; focus returns to `returnId`. */
export type DrawerTarget =
    | { readonly kind: 'edit'; readonly path: SlotPath; readonly returnId: string }
    | { readonly kind: 'add'; readonly parent: SlotPath; readonly addition: 'property' | 'item'; readonly returnId: string };

export interface PropertyDrawerProps {
    readonly tree: Tree;
    readonly target: DrawerTarget;
    readonly catalog: FieldCatalog | null;
    readonly catalogState: 'loading' | 'failed' | 'done' | 'idle';
    readonly candidates: readonly IncludeCandidate[] | null;
    readonly candidatesState: 'loading' | 'failed' | 'done' | 'idle';
    readonly preview: PreviewResult | null;
    readonly onClose: () => void;
}

interface Working {
    readonly named: boolean;
    readonly name: string;
    readonly when: string | null;
    readonly value: Value;
}

const NAME_ID = 'rs-editor-property-name';
const WHEN_ID = 'rs-editor-when';
const EMPTY_FIELD: Value<'field'> = Object.freeze({ kind: 'field', path: '', filters: [], fallback: null });

const parentPathOf = (target: DrawerTarget): SlotPath => (target.kind === 'edit' ? target.path.slice(0, -1) : target.parent);

function startingWork(tree: Tree, target: DrawerTarget): Working {
    if (target.kind === 'add') {
        return { named: target.addition === 'property', name: '', when: null, value: EMPTY_FIELD };
    }
    const slot = slotAt(tree, target.path);
    return { named: isProperty(slot), name: isProperty(slot) ? slot.name : '', when: slot.when, value: slot.value };
}

/** The names of the objects on the way to `parent`, and the type of the object there. */
function objectAt(tree: Tree, parent: SlotPath): { readonly path: string; readonly type: string } {
    const names: string[] = [];
    let type = rootType(tree);
    let slots: readonly Slot[] | null = tree.properties;
    for (const index of parent) {
        const slot: Slot | undefined = slots?.[index];
        if (slot === undefined) {
            break;
        }
        if (isProperty(slot)) {
            names.push(slot.name);
        }
        const value = slot.value.kind === 'list' && slot.value.source.kind === 'store' ? slot.value.source.item : slot.value;
        type = value.kind === 'object' ? firstType(value.types) : type;
        slots = childrenOf(slot.value);
    }
    return { path: names.join('.'), type };
}

/** The names the items of the store lists on the way to a slot are read under. */
function itemNamesOn(tree: Tree, parent: SlotPath, catalog: FieldCatalog | null): ReadonlySet<string> {
    const names = new Set<string>();
    let slots: readonly Slot[] | null = tree.properties;
    for (const index of parent) {
        const slot: Slot | undefined = slots?.[index];
        if (slot === undefined) {
            break;
        }
        if (slot.value.kind === 'list' && slot.value.source.kind === 'store') {
            const source = slot.value.source;
            names.add(source.as ?? catalog?.lists.find((list) => list.path === source.each)?.itemName ?? '');
        }
        slots = childrenOf(slot.value);
    }
    return names;
}

interface GivesProps {
    readonly tree: Tree;
    readonly path: SlotPath;
    readonly work: Working;
    readonly preview: PreviewResult | null;
}

function Gives({ tree, path, work, preview }: GivesProps): JSX.Element | null {
    const { translator: { t } } = usePage();
    const found = slotOutput(tree, path, preview?.output ?? null);
    if (preview === null || found === null) {
        return null;
    }
    const absent = (): string => {
        if (work.value.kind === 'object' && work.value.properties.length === 0) {
            return t('editor.gives.emptyObject');
        }
        return work.when === null ? t('editor.gives.empty', preview.sample.label) : t('editor.gives.skipped', preview.sample.label, work.when);
    };
    return (
        <section class="rs-editor__gives" aria-label={t('editor.gives.title', preview.sample.label)}>
            <h3 class="rs-editor__gives-title">{t('editor.gives.title', preview.sample.label)}</h3>
            {found.kind === 'value'
                ? <pre class="rs-editor__gives-value">{JSON.stringify(found.value, null, 2)}</pre>
                : <p class="rs-editor__muted">{absent()}</p>}
        </section>
    );
}

export function PropertyDrawer({ tree, target, catalog, catalogState, candidates, candidatesState, preview, onClose }: PropertyDrawerProps): JSX.Element {
    const { translator: { t } } = usePage();
    const { store, defaults, canChange } = useEditorEnv();
    const kinds = store.state().draft.pageKinds;
    const [work, setWork] = useState<Working>(() => startingWork(tree, target));
    const [touched, setTouched] = useState(false);
    const parent = parentPathOf(target);
    const siblings = childrenAt(tree, parent);
    const ownIndex = target.kind === 'edit' ? target.path[target.path.length - 1] : -1;
    const taken = siblings.filter((slot, index) => index !== ownIndex && isProperty(slot)).flatMap((slot) => (isProperty(slot) ? [slot.name] : []));
    const nameOk = !work.named || isUsableName(work.name, taken);
    const ready = canChange && nameOk && isComplete(work.value) && (work.when === null || work.when !== '');
    const object = objectAt(tree, parent);
    const suggestions = target.kind === 'add' && work.named ? suggestionsFor(catalog, rootType(tree), object.path, object.type, taken) : [];
    const itemNames = itemNamesOn(tree, target.kind === 'edit' ? target.path : [...parent, -1], catalog);
    const whenOptions = catalog === null
        ? []
        : conditionGroups(catalog, kinds).flatMap((group) => group.fields.map((field) => ({ value: field.path, label: `${group.label} › ${field.label}` })));
    if (work.when !== null && work.when !== '' && !whenOptions.some((option) => option.value === work.when)) {
        whenOptions.unshift({ value: work.when, label: fieldName(catalog, work.when) });
    }

    const slotOf = (): Slot => {
        const slot: Slot = { when: work.when, value: work.value };
        const property: Property = { ...slot, name: work.name };
        return work.named ? property : slot;
    };
    const apply = (): void => {
        if (!ready) {
            setTouched(true);
            return;
        }
        const next = target.kind === 'edit' ? replaceSlot(tree, target.path, slotOf()) : appendSlot(tree, target.parent, slotOf()).tree;
        store.change((draft) => withTree(draft, next));
        onClose();
    };
    const remove = (): void => {
        if (target.kind === 'edit') {
            store.change((draft) => withTree(draft, removeSlot(tree, target.path)));
        }
        onClose();
    };
    const title = target.kind === 'add'
        ? t(work.named ? 'editor.drawer.add' : 'editor.row.new')
        : work.named ? (work.name === '' ? t('editor.row.new') : work.name) : t('editor.drawer.item', (ownIndex ?? 0) + 1);

    return (
        <Drawer
            title={title}
            eyebrow={t('editor.drawer.property')}
            titleStyle={target.kind === 'edit' && work.named ? 'code' : 'text'}
            onClose={onClose}
            returnFocusId={target.returnId}
            actions={(
                <>
                    <Button variant="primary" disabled={!ready} onClick={apply}>
                        {t(target.kind === 'add' ? 'editor.drawer.addButton' : 'editor.drawer.done')}
                    </Button>
                    <Button variant="secondary" onClick={onClose}>{t('editor.drawer.cancel')}</Button>
                </>
            )}
            {...(target.kind === 'edit' && canChange ? { destructive: { label: t('editor.drawer.remove'), onClick: remove } } : {})}
        >
            <div class="rs-editor__drawer">
                {suggestions.length === 0 ? null : (
                    <section class="rs-editor__suggestions">
                        <h3 class="rs-editor__suggestions-title">{t('editor.drawer.suggestions', object.type)}</h3>
                        <div class="rs-editor__suggestion-list">
                            {suggestions.map((suggestion) => (
                                <Button
                                    key={suggestion.name}
                                    variant="secondary"
                                    icon={suggestion.required ? 'priority_high' : 'add'}
                                    onClick={() => setWork({
                                        ...work,
                                        name: suggestion.name,
                                        value: suggestion.field === null ? work.value : { ...EMPTY_FIELD, path: suggestion.field },
                                    })}
                                >
                                    {suggestion.name}
                                </Button>
                            ))}
                        </div>
                        <p class="rs-editor__muted">{t('editor.drawer.suggestionsHelp')}</p>
                    </section>
                )}
                {work.named ? (
                    <FieldRow controlId={NAME_ID} label={t('editor.drawer.name')} problems={touched && !nameOk ? [t('editor.drawer.nameError')] : []}>
                        <TextInput
                            id={NAME_ID}
                            value={work.name}
                            mono
                            invalid={touched && !nameOk}
                            disabled={!canChange}
                            onBlur={() => setTouched(true)}
                            onInput={(name) => setWork({ ...work, name, value: namedValue(catalog, object.type, work.name, name, work.value) })}
                        />
                    </FieldRow>
                ) : null}
                <RadioCards
                    name="rs-editor-kind"
                    label={t('editor.drawer.value')}
                    value={work.value.kind}
                    disabled={!canChange}
                    options={VALUE_KINDS.map((kind) => ({
                        value: kind,
                        label: t(`editor.kind.${kind}`),
                        description: t(`editor.kind.${kind}.text`),
                        icon: KIND_ICONS[kind],
                    }))}
                    onChange={(kind) => {
                        const picked = VALUE_KINDS.find((item): item is ValueKind => item === kind);
                        if (picked !== undefined) {
                            setWork({ ...work, value: valueOfKind(picked, work.value, defaults) });
                        }
                    }}
                />
                <KindEditor
                    key={work.value.kind}
                    value={work.value}
                    onChange={(value) => setWork({ ...work, value })}
                    context={{ name: work.name, catalog, catalogState, candidates, candidatesState, kinds, itemNames, defaults, disabled: !canChange }}
                />
                <section class="rs-editor__when">
                    <h3 class="rs-editor__when-title">{t('editor.when.label')}</h3>
                    <Switch
                        id={`${WHEN_ID}-switch`}
                        checked={work.when !== null}
                        label={t('editor.when.switch')}
                        disabled={!canChange}
                        onChange={(on) => setWork({ ...work, when: on ? defaults.when : null })}
                    />
                    {work.when === null ? null : (
                        <div class="rs-editor__when-field">
                            <SelectInput
                                id={WHEN_ID}
                                value={work.when}
                                label={t('editor.when.field')}
                                options={whenOptions.length === 0 ? [{ value: work.when, label: work.when }] : whenOptions}
                                disabled={!canChange}
                                onChange={(when) => setWork({ ...work, when })}
                            />
                            <span class="rs-editor__muted">{t('editor.when.has')}</span>
                        </div>
                    )}
                </section>
                {target.kind === 'edit' ? <Gives tree={tree} path={target.path} work={work} preview={preview} /> : null}
            </div>
        </Drawer>
    );
}
