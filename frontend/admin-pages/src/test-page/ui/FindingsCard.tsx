/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The card "Findings": the findings of the last check, worst first, as rows that open the pages behind each — its
 * glyph and edge in its tone, what is missing, the snippets concerned, and how many pages; while a check is on its
 * way, that the last findings stay on the Overview until it is finished; when the last check found nothing, so; and
 * before any check, that the findings come after the first. Findings that could not be read anew say so above the
 * ones shown.
 */
import type { JSX } from 'preact';
import { CardFrame, CardState, failureText, Icon, Notice, useKit, usePage } from '@hryvinskyi/admin-ui';
import type { CatalogControls } from '../application/use-catalog.ts';
import { FINDING_ICONS, findingsBody, isChecking, type FindingsBody } from '../domain/catalog-view.ts';
import type { Finding } from '../domain/model.ts';

export interface FindingsCardProps {
    readonly controls: CatalogControls;
}

function FindingRow({ finding, open, onOpen }: {
    readonly finding: Finding;
    readonly open: boolean;
    readonly onOpen: () => void;
}): JSX.Element {
    const { translator } = usePage();
    return (
        <button
            type="button"
            id={`rs-finding-${finding.key}`}
            class="rs-test__finding"
            data-tone={finding.tone}
            data-open={open ? 'true' : undefined}
            aria-label={finding.label}
            onClick={onOpen}
        >
            <span class="rs-test__finding-what">
                <Icon name={FINDING_ICONS[finding.tone]} size={20} filled class="rs-test__finding-glyph" />
                <span class="rs-test__finding-words">
                    <span class="rs-test__finding-title">{finding.title}</span>
                    <span class="rs-test__finding-text">{finding.description}</span>
                </span>
            </span>
            <span class="rs-test__finding-snippets">{translator.list(finding.snippets.map((snippet) => snippet.name))}</span>
            <span class="rs-test__finding-pages">{translator.n(finding.pages)}</span>
            <Icon name="chevron_right" size={20} class="rs-test__finding-chevron" />
        </button>
    );
}

export function FindingsCard({ controls }: FindingsCardProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const { status, findings, drawer } = controls.state;
    const { table } = findings;
    const bodies: { readonly [B in FindingsBody]: () => JSX.Element } = {
        checking: () => (
            <CardState kind="empty" icon="hourglass_top" title={t('test.findings.waitingTitle')} text={t('test.findings.waitingText')} />
        ),
        rows: () => (
            <div class="rs-test__findings">
                <div class="rs-test__findings-head" aria-hidden="true">
                    <span>{t('test.findings.what')}</span>
                    <span>{t('test.findings.snippet')}</span>
                    <span class="rs-test__findings-count">{t('test.findings.pages')}</span>
                    <span />
                </div>
                {table.findings.map((finding) => (
                    <FindingRow
                        key={finding.key}
                        finding={finding}
                        open={drawer?.key === finding.key}
                        onOpen={() => controls.open(finding)}
                    />
                ))}
            </div>
        ),
        clean: () => (
            <CardState kind="empty" icon="check_circle" title={t('test.findings.cleanTitle')} text={t('test.findings.cleanText')} />
        ),
        none: () => (
            <CardState kind="empty" icon="rule" title={t('test.findings.noneTitle')} text={t('test.findings.noneText')} />
        ),
    };
    const body = findingsBody(status, table);
    return (
        <CardFrame
            icon="rule"
            title={t('test.findings.title')}
            subtitle={isChecking(status) || findings.status === 'loading' ? t('test.findings.checking') : table.subtitle}
        >
            {findings.status === 'failed' ? (
                <div class="rs-test__findings-failed">
                    <Notice tone="error">{failureText(findings.failure, kit, t('test.findings.failed'))}</Notice>
                </div>
            ) : null}
            <div class="rs-test__findings-body" data-body={body}>{bodies[body]()}</div>
        </CardFrame>
    );
}
