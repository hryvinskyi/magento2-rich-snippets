/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The add drawer of one template: its type and whether it is a template or a building block; a warning when the
 * shop has it already; why the last attempt was refused or failed; what it writes; where it is output; for a template
 * that asks, where the shop keeps each value — a select pre-filled with the suggested answer and the hint of the
 * answer chosen, in its tone; the building blocks it uses, and whether the shop has each; and what Google does with
 * it. The footer adds it switched off (or adds the new version beside the copy), or cancels.
 */
import type { JSX } from 'preact';
import { useId } from 'preact/hooks';
import {
    Badge,
    Button,
    Drawer,
    failureText,
    Icon,
    Notice,
    SelectInput,
    useKit,
    usePage,
    worded,
} from '@hryvinskyi/admin-ui';
import { addButtonOf, againNoticeOf, chosenOptionOf, refusalOf, useLineOf } from '../domain/add-view.ts';
import type { HintTone, LibraryTemplate, QuestionAnswers } from '../domain/model.ts';
import type { Drawer as DrawerState, QuestionsState } from '../application/library-state.ts';
import { GOOGLE_LOOK } from './TemplateCard.tsx';

export interface AddDrawerProps {
    readonly template: LibraryTemplate;
    readonly drawer: DrawerState;
    readonly onChoose: (question: string, answer: string) => void;
    readonly onRetryQuestions: () => void;
    readonly onAdd: () => void;
    readonly onClose: () => void;
}

const HINT_GLYPH = { ok: 'check_circle', warn: 'warning' } as const satisfies Record<HintTone, string>;

interface QuestionProps {
    readonly question: QuestionAnswers;
    readonly chosen: ReadonlyMap<string, string>;
    readonly onChoose: (question: string, answer: string) => void;
}

function Question({ question, chosen, onChoose }: QuestionProps): JSX.Element {
    const id = useId();
    const option = chosenOptionOf(question, chosen);
    return (
        <div class="hui-field rs-add__question">
            <label class="hui-field__label" for={id}>{question.label}</label>
            <SelectInput
                id={id}
                width="full"
                value={option?.key ?? question.default}
                options={question.options.map((offered) => ({ value: offered.key, label: offered.label }))}
                describedBy={option === null || option.hint === '' ? undefined : `${id}-hint`}
                onChange={(answer) => onChoose(question.question, answer)}
            />
            {option === null || option.hint === '' ? null : (
                <span id={`${id}-hint`} class="rs-add__hint" data-tone={option.tone}>
                    <Icon name={HINT_GLYPH[option.tone]} size={16} filled class="rs-add__hint-glyph" />
                    {option.hint}
                </span>
            )}
        </div>
    );
}

interface QuestionsProps {
    readonly template: LibraryTemplate;
    readonly state: QuestionsState;
    readonly chosen: ReadonlyMap<string, string>;
    readonly onChoose: (question: string, answer: string) => void;
    readonly onRetry: () => void;
}

function Questions({ template, state, chosen, onChoose, onRetry }: QuestionsProps): JSX.Element | null {
    const { t } = usePage().translator;
    const bodies: { readonly [S in QuestionsState['status']]: (current: QuestionsState & { readonly status: S }) => JSX.Element | null } = {
        none: () => null,
        loading: () => (
            <div class="rs-add__loading" aria-busy="true">
                {template.questions.map((question) => (
                    <div key={question.code} class="hui-field">
                        <span class="hui-field__label">{question.label}</span>
                        <span class="rs-add__loading-line">{t('library.questions.loading')}</span>
                    </div>
                ))}
            </div>
        ),
        ready: (current) => (
            <>
                {current.answers.questions.map((question) => (
                    <Question key={question.question} question={question} chosen={chosen} onChoose={onChoose} />
                ))}
            </>
        ),
        failed: () => (
            <Notice tone="warning" action={{ label: t('library.questions.retry'), onClick: onRetry }}>
                {t('library.questions.failed')}
            </Notice>
        ),
    };
    if (template.questions.length === 0) {
        return null;
    }
    return (
        <section class="rs-add__group" aria-labelledby="rs-add-questions">
            <span class="rs-add__group-head">
                <h3 id="rs-add-questions" class="rs-add__group-title">{t('library.questions.title')}</h3>
                <span class="rs-add__group-text">{t('library.questions.text')}</span>
            </span>
            {bodyOf(bodies, state)}
        </section>
    );
}

function bodyOf<S extends QuestionsState['status']>(
    bodies: { readonly [P in QuestionsState['status']]: (current: QuestionsState & { readonly status: P }) => JSX.Element | null },
    state: QuestionsState & { readonly status: S },
): JSX.Element | null {
    return bodies[state.status](state);
}

export function AddDrawer({ template, drawer, onChoose, onRetryQuestions, onAdd, onClose }: AddDrawerProps): JSX.Element {
    const { translator } = usePage();
    const kit = useKit().translator;
    const { t } = translator;
    const again = againNoticeOf(template);
    const look = GOOGLE_LOOK[template.google.status];
    return (
        <Drawer
            title={t('library.drawer.title', template.name)}
            aside={(
                <span class="rs-add__sub">
                    {template.type === '' ? null : <Badge variant="code">{template.type}</Badge>}
                    {t(template.isBlock ? 'library.drawer.block' : 'library.drawer.template')}
                </span>
            )}
            onClose={onClose}
            actions={(
                <>
                    <Button variant="primary" iconEnd="check" busy={drawer.adding} onClick={onAdd}>
                        {drawer.adding ? t('library.drawer.adding') : worded(translator, addButtonOf(template))}
                    </Button>
                    <Button variant="secondary" onClick={onClose}>{t('library.drawer.cancel')}</Button>
                </>
            )}
        >
            {again === null ? null : <Notice tone="warning">{worded(translator, again)}</Notice>}
            {drawer.refused === null ? null : <Notice tone="error">{worded(translator, refusalOf(drawer.refused))}</Notice>}
            {drawer.failure === null ? null : (
                <Notice tone="error">{failureText(drawer.failure, kit, t('library.failed.add'))}</Notice>
            )}
            <section class="rs-add__part" aria-labelledby="rs-add-writes">
                <h3 id="rs-add-writes" class="rs-add__part-title">{t('library.drawer.writes')}</h3>
                <div class="hui-actions rs-add__properties">
                    {template.properties.map((property) => <Badge key={property} variant="code">{property}</Badge>)}
                </div>
            </section>
            <section class="rs-add__part" aria-labelledby="rs-add-where">
                <h3 id="rs-add-where" class="rs-add__part-title">{t('library.drawer.where')}</h3>
                <p class="rs-add__where">{`${template.where}.`}</p>
                <p class="rs-add__note">{t('library.drawer.whereNote')}</p>
            </section>
            <Questions
                template={template}
                state={drawer.questions}
                chosen={drawer.chosen}
                onChoose={onChoose}
                onRetry={onRetryQuestions}
            />
            {template.uses.length === 0 ? null : (
                <section class="rs-add__group" aria-labelledby="rs-add-blocks">
                    <h3 id="rs-add-blocks" class="rs-add__group-title">{t('library.blocks.title')}</h3>
                    <ul class="rs-add__blocks">
                        {template.uses.map((use) => (
                            <li key={use.code} class="rs-add__block">
                                <Icon name="widgets" size={20} class="rs-add__block-glyph" />
                                <span class="rs-add__block-words">
                                    <span class="rs-add__block-name">{use.name}</span>
                                    <span class="rs-add__block-line">{worded(translator, useLineOf(use))}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <section class="rs-add__part rs-add__part--ruled" aria-labelledby="rs-add-google">
                <h3 id="rs-add-google" class="rs-add__part-title">{t('library.drawer.google')}</h3>
                <p class="rs-add__google" data-google={template.google.status}>
                    <Icon name={look.icon} size={18} filled={look.filled} class="rs-library__google-glyph" />
                    <span>{template.google.long}</span>
                </p>
            </section>
        </Drawer>
    );
}
