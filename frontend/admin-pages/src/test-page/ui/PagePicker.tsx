/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The page picker: a field drawn as the bar's strong select, naming the page picked. Focusing it opens a list of the
 * shop's pages; typing narrows the list by name, SKU or address, and a text that is a storefront path is offered as
 * any address. ↑ and ↓ move through the list, Enter or a click picks, Escape and leaving the field close it with the
 * page picked as it was (a combobox with a list, as the platform's own pattern describes it).
 */
import type { JSX } from 'preact';
import { useId, useState } from 'preact/hooks';
import { failureText, Icon, itemAfterKey, useKit, usePage, worded, type Translator } from '@hryvinskyi/admin-ui';
import type { PageSearch } from '../application/use-page-search.ts';
import type { PickedPage } from '../domain/model.ts';
import { pickerName } from '../domain/page-view.ts';

export interface PagePickerProps {
    readonly id: string;
    readonly page: PickedPage | null;
    readonly store: number;
    readonly search: PageSearch;
    readonly onPick: (page: PickedPage) => void;
}

/** How the picker names a page. */
export const pageName = (translator: Translator, page: PickedPage): string => {
    const name = pickerName(page);
    return name === null ? page.label : worded(translator, name);
};

export function PagePicker({ id, page, store, search, onPick }: PagePickerProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const list = useId();
    const [query, setQuery] = useState<string | null>(null);
    const [active, setActive] = useState(-1);
    const open = query !== null;
    const options = search.state.status === 'ready' ? search.state.options : [];

    const close = (): void => {
        setQuery(null);
        setActive(-1);
        search.stop();
    };
    const pick = (chosen: PickedPage): void => {
        close();
        onPick(chosen);
    };
    const start = (): void => {
        if (!open) {
            setQuery('');
            setActive(-1);
            search.find('', store, true);
        }
    };
    const onInput = (text: string): void => {
        setQuery(text);
        setActive(-1);
        search.find(text.trim(), store, false);
    };
    const onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && open) {
            event.preventDefault();
            close();
            return;
        }
        if (event.key === 'Enter' && open) {
            event.preventDefault();
            const chosen = options[active] ?? (options.length === 1 ? options[0] : undefined);
            if (chosen !== undefined) {
                pick(chosen);
            }
            return;
        }
        if (!open && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            start();
            return;
        }
        const next = itemAfterKey(active, options.length, event.key);
        if (next !== null) {
            event.preventDefault();
            setActive(next);
        }
    };
    const optionId = (index: number): string => `${list}-${String(index)}`;
    const notes: { readonly [S in PageSearch['state']['status']]: () => string | null } = {
        idle: () => null,
        loading: () => t('test.picker.searching'),
        ready: () => (options.length === 0 ? t('test.picker.none') : null),
        failed: () => (search.state.status === 'failed'
            ? failureText(search.state.failure, kit, t('test.picker.failed'))
            : null),
    };
    const note = notes[search.state.status]();
    return (
        <div class="hui-select hui-select--strong rs-test__picker">
            <input
                id={id}
                type="text"
                class="hui-select__control rs-test__picker-field"
                role="combobox"
                aria-expanded={open}
                aria-controls={list}
                aria-autocomplete="list"
                aria-activedescendant={open && active >= 0 && active < options.length ? optionId(active) : undefined}
                autoComplete="off"
                spellcheck={false}
                placeholder={t('test.picker.placeholder')}
                value={open ? query : (page === null ? '' : pageName(translator, page))}
                onFocus={start}
                onClick={start}
                onBlur={close}
                onInput={(event) => onInput(event.currentTarget.value)}
                onKeyDown={onKeyDown}
            />
            <Icon name="expand_more" size={20} class="hui-select__chevron" />
            <div class="rs-test__options" hidden={!open}>
                <ul id={list} role="listbox" aria-label={t('test.picker.results')} class="rs-test__option-list">
                    {open ? options.map((option, index) => (
                        <li
                            key={`${option.kind}:${option.ref ?? option.path}`}
                            id={optionId(index)}
                            role="option"
                            class="rs-test__option"
                            aria-selected={index === active}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(option);
                            }}
                        >
                            {pageName(translator, option)}
                        </li>
                    )) : null}
                </ul>
                {open && note !== null ? <p class="rs-test__options-note" role="status">{note}</p> : null}
            </div>
        </div>
    );
}
