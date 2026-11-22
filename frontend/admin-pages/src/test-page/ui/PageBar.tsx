/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The bar of the "One page" mode, under the module navigation: the page picker, the store view the page is read in
 * (each view by name; there is no "every store view" here), Test, and a line saying what is read now, how long the
 * last test took, or why it gave nothing. Picking a page or a store view tests it at once.
 */
import type { JSX } from 'preact';
import { useId } from 'preact/hooks';
import { Button, Icon, SelectInput, usePage, worded, type PageStoreView } from '@hryvinskyi/admin-ui';
import type { PageSearch } from '../application/use-page-search.ts';
import type { PageTestControls } from '../application/use-page-test.ts';
import { runLineOf } from '../application/page-test-state.ts';
import { runLine, whereOf } from '../domain/page-view.ts';
import { PagePicker } from './PagePicker.tsx';

export interface PageBarProps {
    readonly controls: PageTestControls;
    readonly search: PageSearch;
    readonly stores: readonly PageStoreView[];
}

export function PageBar({ controls, search, stores }: PageBarProps): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const id = useId();
    const { page, store, run } = controls.state;
    const busy = run.status === 'busy';
    const line = runLineOf(controls.state);
    const durationMs = run.status === 'done' ? run.test.durationMs : 0;
    return (
        <div class="rs-test__bar">
            <label class="rs-test__bar-label" for={`${id}-page`}>
                <Icon name="web" size={18} />
                {t('test.bar.page')}
            </label>
            <PagePicker id={`${id}-page`} page={page} store={store} search={search} onPick={controls.pick} />
            <SelectInput
                width="auto"
                label={t('page.store.label')}
                value={String(store)}
                options={stores.map((view) => ({ value: String(view.id), label: view.name }))}
                onChange={(value) => controls.chooseStore(Number(value))}
            />
            <Button
                variant="control"
                icon={busy ? 'progress_activity' : 'play_arrow'}
                busy={busy}
                disabled={page === null}
                onClick={controls.retest}
            >
                {t(busy ? 'test.bar.running' : 'test.bar.run')}
            </Button>
            <span class="rs-test__bar-line" role="status">
                {line === null ? null : worded(translator, runLine(line, page === null ? '' : whereOf(page), durationMs))}
            </span>
        </div>
    );
}
