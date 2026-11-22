/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The import drawer: the file box (its name, what it holds or its size, and *Choose another*), then — for a file the
 * server refused — why, in words that say how to recognise the right file and that nothing was changed; or "In the
 * file": each snippet with its type, how it stands against the shop's, the problems found in it, a choice of what to
 * do with it and what that choice does; then the note that added snippets arrive switched off. The footer imports as
 * many snippets as the choices add or replace, or does nothing while they add none.
 */
import type { JSX } from 'preact';
import { useRef } from 'preact/hooks';
import {
    Badge,
    Button,
    Drawer,
    failureText,
    Icon,
    Notice,
    SegmentedControl,
    useKit,
    usePage,
    worded,
    type RequestFailure,
    type Translator,
} from '@hryvinskyi/admin-ui';
import {
    effectLine,
    fileMeta,
    fileRefusalOf,
    fileSize,
    importedCount,
    problemOf,
    runLabel,
    statusLine,
    type ExistingSwitch,
} from '../domain/import-view.ts';
import type { ImportChoice, ImportEntry, SnippetRow } from '../domain/model.ts';
import type { ImportState, ImportStep } from '../application/import-state.ts';
import type { ChosenFile } from '../application/ports.ts';
import { useSnippetsEnv } from './snippets-env.tsx';

export interface ImportDrawerProps {
    readonly state: ImportState;
    /** The rows of the list on screen, to tell whether a snippet the file replaces is on. */
    readonly rows: readonly SnippetRow[];
    readonly onChoose: (file: ChosenFile) => void;
    readonly onPick: (code: string, choice: ImportChoice) => void;
    readonly onCommit: () => void;
    readonly onClose: () => void;
}

/** What a failure says in the drawer: the reason's own words, the kit's for its kind, or the place's. */
function failureWords(failure: RequestFailure, translator: Translator, kit: Translator, placeKey: string): string {
    const refusal = failure.kind === 'rejected' ? fileRefusalOf(failure.reason) : null;
    return refusal === null ? failureText(failure, kit, translator.t(placeKey)) : worded(translator, refusal);
}

interface FileBoxProps {
    readonly name: string | null;
    readonly meta: string;
    readonly bad: boolean;
    readonly onChoose: (file: ChosenFile) => void;
}

function FileBox({ name, meta, bad, onChoose }: FileBoxProps): JSX.Element {
    const { t } = usePage().translator;
    const input = useRef<HTMLInputElement>(null);
    return (
        <div class="rs-import__file" data-bad={bad ? 'true' : 'false'}>
            <Icon name="description" size={24} class="rs-import__file-icon" />
            <span class="rs-import__file-words">
                {name === null ? null : <span class="rs-import__file-name">{name}</span>}
                <span class="rs-import__file-meta">{meta}</span>
            </span>
            <Button variant="link" onClick={() => input.current?.click()}>
                {t(name === null ? 'snippets.import.choose' : 'snippets.import.chooseAnother')}
            </Button>
            <input
                ref={input}
                type="file"
                accept="application/json,.json"
                class="hui-sr-only"
                tabIndex={-1}
                aria-label={t('snippets.import.file.label')}
                onChange={(event) => {
                    const file = event.currentTarget.files?.[0];
                    event.currentTarget.value = '';
                    if (file !== undefined) {
                        onChoose(file);
                    }
                }}
            />
        </div>
    );
}

interface EntryProps {
    readonly entry: ImportEntry;
    readonly choice: ImportChoice | undefined;
    readonly existing: ExistingSwitch;
    readonly onPick: (code: string, choice: ImportChoice) => void;
}

function Entry({ entry, choice, existing, onPick }: EntryProps): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    const { days, kindsByCode } = useSnippetsEnv();
    return (
        <li class="rs-import__entry">
            <span class="rs-import__entry-head">
                <span class="rs-import__entry-title">
                    <span class="rs-import__entry-name">{entry.name}</span>
                    {entry.type === '' ? null : <Badge variant="code">{entry.type}</Badge>}
                </span>
                <span class="rs-import__entry-status">{statusLine(entry, translator, days)}</span>
                {entry.problems.map((problem, index) => (
                    <span key={`${problem.reason}-${String(index)}`} class="rs-import__entry-problem">
                        {worded(translator, problemOf(problem))}
                    </span>
                ))}
            </span>
            {choice === undefined ? null : (
                <>
                    <SegmentedControl
                        label={entry.name}
                        value={choice}
                        options={entry.choices.map((offered) => ({ value: offered, label: t(`snippets.import.choice.${offered}`) }))}
                        onChange={(value) => {
                            const picked = entry.choices.find((offered) => offered === value);
                            if (picked !== undefined) {
                                onPick(entry.code, picked);
                            }
                        }}
                    />
                    <span class="rs-import__entry-effect">{effectLine(entry, choice, existing, kindsByCode, translator)}</span>
                </>
            )}
        </li>
    );
}

export function ImportDrawer({ state, rows, onChoose, onPick, onCommit, onClose }: ImportDrawerProps): JSX.Element | null {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const { days } = useSnippetsEnv();
    const existingOf = (entry: ImportEntry): ExistingSwitch =>
        rows.find((row) => row.id === entry.existingId)?.active ?? null;
    const bodies: { readonly [S in ImportStep]: (current: ImportState<S>) => JSX.Element | null } = {
        closed: () => null,
        choosing: () => <FileBox name={null} meta={t('snippets.import.file.none')} bad={false} onChoose={onChoose} />,
        reading: (current) => <FileBox name={current.name} meta={t('snippets.import.reading')} bad={false} onChoose={onChoose} />,
        refused: (current) => (
            <>
                <FileBox name={current.name} meta={fileSize(current.size, translator)} bad onChoose={onChoose} />
                <Notice tone="error" role="alert">
                    {failureWords(current.failure, translator, kit, 'snippets.failed.preview')}
                </Notice>
            </>
        ),
        preview: (current) => (
            <>
                <FileBox name={current.file.name} meta={fileMeta(current.preview, translator, days)} bad={false} onChoose={onChoose} />
                {current.failure === null ? null : (
                    <Notice tone="error" role="alert">
                        {failureWords(current.failure, translator, kit, 'snippets.failed.import')}
                    </Notice>
                )}
                <section class="rs-import__section" aria-labelledby="rs-import-in-file">
                    <h3 id="rs-import-in-file" class="rs-import__section-title">{t('snippets.import.inFile')}</h3>
                    <ul class="rs-import__entries">
                        {current.preview.snippets.map((entry) => (
                            <Entry
                                key={entry.code}
                                entry={entry}
                                choice={current.choices.get(entry.code)}
                                existing={existingOf(entry)}
                                onPick={onPick}
                            />
                        ))}
                    </ul>
                </section>
                <Notice tone="info" muted role="none">{t('snippets.import.note')}</Notice>
            </>
        ),
    };
    if (state.step === 'closed') {
        return null;
    }
    const count = state.step === 'preview' ? importedCount(state.choices) : 0;
    const committing = state.step === 'preview' && state.committing;
    return (
        <Drawer
            title={t('snippets.import.title')}
            aside={<span class="rs-import__sub">{t('snippets.import.sub')}</span>}
            onClose={onClose}
            actions={(
                <>
                    <Button
                        variant="primary"
                        iconEnd="check"
                        disabled={count === 0}
                        busy={committing}
                        onClick={onCommit}
                    >
                        {committing ? t('snippets.import.running') : runLabel(count, translator)}
                    </Button>
                    <Button variant="secondary" onClick={onClose}>{t('snippets.import.cancel')}</Button>
                </>
            )}
        >
            {bodyOf(bodies, state)}
        </Drawer>
    );
}

function bodyOf<S extends ImportStep>(
    bodies: { readonly [P in ImportStep]: (current: ImportState<P>) => JSX.Element | null },
    state: ImportState<S>,
): JSX.Element | null {
    return bodies[state.step](state);
}
