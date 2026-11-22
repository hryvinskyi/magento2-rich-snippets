/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search box in the card's head: a search glyph, an input named and hinted by the page's words, and nothing
 * else; every keystroke is handed on.
 */
import type { JSX } from 'preact';
import { Icon } from '@hryvinskyi/admin-ui';

export interface SearchFieldProps {
    readonly value: string;
    /** Names the input and is its placeholder; translated. */
    readonly label: string;
    readonly disabled: boolean;
    readonly onInput: (value: string) => void;
}

export function SearchField({ value, label, disabled, onInput }: SearchFieldProps): JSX.Element {
    return (
        <label class="rs-library__search" data-disabled={disabled ? 'true' : 'false'}>
            <Icon name="search" size={18} class="rs-library__search-glyph" />
            <span class="hui-sr-only">{label}</span>
            <input
                type="search"
                class="rs-library__search-input"
                value={value}
                placeholder={label}
                disabled={disabled}
                spellcheck={false}
                autocomplete="off"
                onInput={(event) => onInput(event.currentTarget.value)}
            />
        </label>
    );
}
