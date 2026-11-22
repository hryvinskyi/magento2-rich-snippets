/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The card "What this page outputs": every script block the page carries, each a row that opens to its JSON with the
 * snippet's editor and its size, the first open; the markup the theme writes itself, with the way to remove it; what
 * is said when nothing is output; and the snippets that are not output here, each with the reason, open by default
 * only when the page carries no block. While a test reads the page the card shows three placeholder rows; a page
 * that could not be read says why and what still works; a test the server could not run says so in the kit's words
 * for the kind of failure, or the page's. Every link is drawn only for an admin who may open its screen.
 */
import type { JSX } from 'preact';
import { useId } from 'preact/hooks';
import {
    Badge,
    CardFrame,
    CardState,
    codeLines,
    failureText,
    Icon,
    LinkButton,
    useKit,
    usePage,
    worded,
    type Translator,
} from '@hryvinskyi/admin-ui';
import type { PageTestControls } from '../application/use-page-test.ts';
import type { RunState } from '../application/page-test-state.ts';
import { jsonTokens } from '../domain/json-tokens.ts';
import type { NotOutput, PageTest, PickedPage, TestedBlock } from '../domain/model.ts';
import {
    BLOCK_LOOKS,
    conditionsLink,
    editorLink,
    kilobytes,
    nothingText,
    outputSummary,
    settingsLink,
    whereOf,
} from '../domain/page-view.ts';

export interface OutputCardProps {
    readonly controls: PageTestControls;
}

/** The reason a snippet that is set for the page but whose conditions it does not meet is not output. */
const CONDITIONS_NOT_MET = 'conditions_not_met';

/** The output card's subtitle for a test's answer. */
function subtitleOf(translator: Translator, where: string, test: PageTest): string {
    const { t, tn } = translator;
    if (test.blocks.length === 0) {
        return t('test.output.subtitleNothing', where);
    }
    const summary = outputSummary(test.blocks);
    const blocks = tn('test.output.blocks', summary.count, translator.n(summary.kilobytes));
    return summary.problems === 0
        ? t('test.output.subtitle', where, blocks)
        : t('test.output.subtitleProblems', where, blocks, tn('test.output.problems', summary.problems));
}

function JsonCode({ block, label }: { readonly block: TestedBlock; readonly label: string }): JSX.Element {
    return (
        <div class="rs-test__code">
            <div class="rs-test__code-scroll" tabIndex={0} role="group" aria-label={label}>
                {codeLines(block.json, jsonTokens(block.json)).map((line) => (
                    <div key={line.key} class="rs-test__code-line">
                        {line.segments.map((segment, index) => (
                            <span key={index} data-kind={segment.kind}>{segment.text}</span>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

interface BlockRowProps {
    readonly block: TestedBlock;
    readonly open: boolean;
    readonly onToggle: () => void;
}

function BlockRow({ block, open, onToggle }: BlockRowProps): JSX.Element {
    const { translator, link } = usePage();
    const { t, tn } = translator;
    const id = useId();
    const look = BLOCK_LOOKS[block.verdict];
    const name = block.name ?? t('test.block.notOurs');
    const edit = block.snippetId === null ? null : link(editorLink(block.snippetId));
    return (
        <div class="rs-test__block" data-bad={look.bad ? 'true' : undefined}>
            <button type="button" class="rs-test__block-head" aria-expanded={open} aria-controls={id} onClick={onToggle}>
                <Icon name={look.icon} size={24} filled class="rs-test__block-glyph" />
                <span class="rs-test__block-words">
                    <span class="rs-test__block-title">
                        <span class="rs-test__block-name">{name}</span>
                        {block.type === '' ? null : <Badge variant="code">{block.type}</Badge>}
                    </span>
                    <span class="rs-test__block-summary">{block.summary}</span>
                </span>
                <span class="rs-test__block-size">{t('test.block.size', translator.n(kilobytes(block.size)))}</span>
                <Icon name={open ? 'expand_less' : 'expand_more'} size={20} class="rs-test__block-chevron" />
            </button>
            {open ? (
                <div id={id} class="rs-test__block-body">
                    <JsonCode block={block} label={t('test.block.output', name)} />
                    <div class="rs-test__block-foot">
                        {edit === null ? <span /> : (
                            <LinkButton href={edit} variant="link">
                                <Icon name="edit" size={14} />
                                {t('test.block.edit')}
                            </LinkButton>
                        )}
                        <span class="rs-test__block-lines">{tn('test.block.foot', block.lines)}</span>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function ThemeMarkup({ text }: { readonly text: string }): JSX.Element {
    const { translator, link } = usePage();
    const settings = link(settingsLink());
    return (
        <div class="rs-test__theme">
            <span class="rs-test__part-title">{translator.t('test.theme.heading')}</span>
            <div class="hui-notice rs-test__theme-notice" data-tone="warning" role="alert">
                <Icon name="warning" size={20} filled class="hui-notice__icon" />
                <div class="hui-notice__text">{text}</div>
                {settings === null ? null : (
                    <div class="hui-notice__action">
                        <LinkButton href={settings} variant="tertiary" forward>{translator.t('test.theme.remove')}</LinkButton>
                    </div>
                )}
            </div>
        </div>
    );
}

function NotOutputList({ rows, open }: { readonly rows: readonly NotOutput[]; readonly open: boolean }): JSX.Element {
    const { translator, link } = usePage();
    return (
        <details class="hui-disclosure rs-test__not" open={open}>
            <summary class="hui-disclosure__summary">{translator.tn('test.notOutput.summary', rows.length)}</summary>
            <div class="hui-disclosure__body rs-test__not-body">
                {rows.map((row) => {
                    const conditions = row.reason === CONDITIONS_NOT_MET && row.snippetId !== null
                        ? link(conditionsLink(row.snippetId))
                        : null;
                    return (
                        <div key={row.code} class="rs-test__not-row">
                            <span class="rs-test__not-name">{row.name}</span>
                            <span class="rs-test__not-why">
                                {row.text}
                                {conditions === null ? null : (
                                    <LinkButton href={conditions} variant="link" forward>
                                        {translator.t('test.notOutput.conditions')}
                                    </LinkButton>
                                )}
                            </span>
                        </div>
                    );
                })}
            </div>
        </details>
    );
}

function Answered({ test, opened, onToggle, onRetry }: {
    readonly test: PageTest;
    readonly opened: ReadonlySet<number>;
    readonly onToggle: (index: number) => void;
    readonly onRetry: () => void;
}): JSX.Element {
    const { translator } = usePage();
    const { t } = translator;
    if (test.failure !== null) {
        return (
            <CardState
                kind="failed"
                title={t('test.fail.title')}
                text={test.failure.text}
                action={{ label: t('test.fail.retry'), onClick: onRetry }}
            />
        );
    }
    const nothing = test.blocks.length === 0;
    return (
        <>
            {test.blocks.map((block, index) => (
                <BlockRow key={index} block={block} open={opened.has(index)} onToggle={() => onToggle(index)} />
            ))}
            {nothing ? (
                <CardState
                    kind="empty"
                    icon="code_off"
                    title={t('test.nothing.title')}
                    text={worded(translator, nothingText(test.page.kind, test.notOutput.length))}
                />
            ) : null}
            {test.themeMarkup === null ? null : <ThemeMarkup text={test.themeMarkup} />}
            {test.notOutput.length === 0 ? null : (
                <NotOutputList key={`${test.page.kind}:${test.page.ref ?? test.page.path}`} rows={test.notOutput} open={nothing} />
            )}
        </>
    );
}

function Placeholder(): JSX.Element {
    const { t } = usePage().translator;
    return (
        <div class="rs-test__skeleton" aria-busy="true" aria-label={t('test.output.busy')}>
            {[0, 1, 2].map((row) => (
                <div key={row} class="rs-test__skeleton-row">
                    <span class="rs-test__skeleton-dot" />
                    <span class="rs-test__skeleton-lines"><span /><span /></span>
                    <span class="rs-test__skeleton-size" />
                </div>
            ))}
        </div>
    );
}

export function OutputCard({ controls }: OutputCardProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const { page, run, opened } = controls.state;
    const where = page === null ? '' : whereOf(page);
    const bodies: { readonly [S in RunState['status']]: (current: RunState & { readonly status: S }) => JSX.Element } = {
        idle: () => (page === null ? <CardState kind="empty" icon="web" title={t('test.bar.line.none')} /> : <Placeholder />),
        busy: () => <Placeholder />,
        done: (current) => (
            <Answered test={current.test} opened={opened} onToggle={controls.toggleBlock} onRetry={controls.retest} />
        ),
        error: (current) => (
            <CardState
                kind="failed"
                title={failureText(current.failure, kit, t('test.error.text'))}
                action={{ label: t('test.fail.retry'), onClick: controls.retest }}
            />
        ),
    };
    return (
        <CardFrame
            icon="code_blocks"
            title={t('test.output.title')}
            subtitle={run.status === 'done' && run.test.failure === null ? subtitleOf(translator, subtitleWhere(page, run.test), run.test) : where}
        >
            <div class="rs-test__output">{bodyOf(bodies, run)}</div>
        </CardFrame>
    );
}

/** Where the subtitle says the page is: the path the test read it at, else where the pick says it is. */
const subtitleWhere = (page: PickedPage | null, test: PageTest): string =>
    test.page.path !== '' ? test.page.path : (page === null ? '' : whereOf(page));

function bodyOf<S extends RunState['status']>(
    bodies: { readonly [P in RunState['status']]: (current: RunState & { readonly status: P }) => JSX.Element },
    run: RunState & { readonly status: S },
): JSX.Element {
    return bodies[run.status](run);
}
