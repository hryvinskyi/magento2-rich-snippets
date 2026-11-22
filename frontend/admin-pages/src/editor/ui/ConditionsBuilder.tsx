/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The expression builder inside the Pages card. The builder draws its own markup into a host element this component
 * never redraws: it is mounted once its configuration is known, mounted again when the configuration changes (its
 * options are fixed while it is mounted), told of a condition tree changed from outside, and destroyed when the card
 * goes away. Every change it reports is handed on.
 */
import type { JSX } from 'preact';
import { useEffect, useRef, useState } from 'preact/hooks';
import { Notice } from '@hryvinskyi/admin-ui';
import type { ConditionsHandle, ConditionsWidgets } from '../application/ports.ts';

export interface ConditionsBuilderProps {
    readonly widgets: ConditionsWidgets;
    readonly config: Readonly<Record<string, unknown>>;
    readonly value: string;
    /** The builder's heading; translated. */
    readonly label: string;
    readonly loadingText: string;
    readonly failedText: string;
    readonly onChange: (value: string) => void;
    readonly report: (error: unknown) => void;
}

export function ConditionsBuilder({ widgets, config, value, label, loadingText, failedText, onChange, report }: ConditionsBuilderProps): JSX.Element {
    const host = useRef<HTMLDivElement>(null);
    const handle = useRef<ConditionsHandle | null>(null);
    const shown = useRef(value);
    const changed = useRef(onChange);
    changed.current = onChange;
    const [state, setState] = useState<'loading' | 'ready' | 'failed'>('loading');

    useEffect(() => {
        let live = true;
        setState('loading');
        widgets.load().then((widget) => {
            const element = host.current;
            if (!live || element === null) {
                return;
            }
            handle.current = widget.mount(element, { value: shown.current, config, label }, (next) => {
                shown.current = next;
                changed.current(next);
            });
            setState('ready');
        }).catch((error: unknown) => {
            if (live) {
                report(error);
                setState('failed');
            }
        });
        return () => {
            live = false;
            handle.current?.destroy();
            handle.current = null;
        };
    }, [widgets, config, label, report]);

    useEffect(() => {
        if (value !== shown.current) {
            shown.current = value;
            handle.current?.setValue(value);
        }
    }, [value]);

    return (
        <div class="rs-editor__conditions">
            {state === 'loading' ? <p class="rs-editor__muted" role="status">{loadingText}</p> : null}
            {state === 'failed' ? <Notice tone="error">{failedText}</Notice> : null}
            <div ref={host} class="rs-editor__conditions-host" data-role="rs-editor-conditions" />
        </div>
    );
}
