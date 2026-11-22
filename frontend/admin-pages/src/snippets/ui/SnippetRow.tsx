/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One snippet of the list: a link to its editor (when the admin may open it) with its glyph, name, type, flags, the
 * line saying where it is output and the line saying what it holds; then a building block's count of the snippets
 * that use it, or a page snippet's switch; then the row menu (Duplicate, Export, Delete). Without the permission to
 * change snippets neither the switch nor the menu is drawn.
 *
 * Delete asks first, in a notice under the row: *Delete* and *Keep it*, or — for a building block snippets use — the
 * names of those snippets and *Close*.
 */
import type { JSX } from 'preact';
import {
    Badge,
    Button,
    Icon,
    linkTo,
    MenuButton,
    Notice,
    StatePill,
    Switch,
    usePage,
    withParam,
    worded,
    type Translator,
} from '@hryvinskyi/admin-ui';
import type { SnippetReference, SnippetRow as Row } from '../domain/model.ts';
import { deleteConfirmOf, type DeleteConfirm, type DeleteConfirmKind } from '../domain/refusals.ts';
import { metaLine, placementLine, rowLook } from '../domain/row-view.ts';
import { useSnippetsEnv } from './snippets-env.tsx';

const EDITOR = 'hryvinskyi_richsnippets/snippets/edit';

export interface SnippetRowProps {
    readonly row: Row;
    /** The switch as shown: the state wanted while its request is on its way. */
    readonly active: boolean;
    readonly menuOpen: boolean;
    /** The question under the row, when it is asked; `includers` are the users the server named. */
    readonly confirm: { readonly includers: readonly SnippetReference[] | null } | null;
    readonly deleting: boolean;
    readonly onSwitch: (active: boolean) => void;
    readonly onMenu: (open: boolean) => void;
    readonly onDuplicate: () => void;
    readonly onExport: () => void;
    readonly onAskDelete: () => void;
    readonly onDelete: () => void;
    readonly onKeep: () => void;
}

interface QuestionProps {
    readonly name: string;
    readonly deleting: boolean;
    readonly onDelete: () => void;
    readonly onKeep: () => void;
}

const QUESTIONS: { readonly [K in DeleteConfirmKind]: (confirm: DeleteConfirm<K>, props: QuestionProps, translator: Translator) => JSX.Element } = {
    ask: (confirm, props, translator) => (
        <Notice tone="error" role="alert">
            <span class="rs-snippets__confirm-line">
                <span>{worded(translator, confirm.question)}</span>
                <span class="hui-actions rs-snippets__confirm-actions">
                    <Button variant="tertiary" onClick={props.onDelete} busy={props.deleting}>{translator.t('snippets.delete.confirm')}</Button>
                    <Button variant="link" onClick={props.onKeep}>{translator.t('snippets.delete.keep')}</Button>
                </span>
            </span>
        </Notice>
    ),
    inUse: (confirm, props, translator) => (
        <Notice tone="warning" role="alert">
            <span class="rs-snippets__confirm-line">
                <span>{translator.tn('snippets.delete.inUse', confirm.users.length, props.name, translator.list(confirm.users))}</span>
                <Button variant="link" onClick={props.onKeep}>{translator.t('snippets.delete.close')}</Button>
            </span>
        </Notice>
    ),
};

function questionOf<K extends DeleteConfirmKind>(confirm: DeleteConfirm<K>, props: QuestionProps, translator: Translator): JSX.Element {
    return QUESTIONS[confirm.kind](confirm, props, translator);
}

export function SnippetRow(props: SnippetRowProps): JSX.Element {
    const { row, active, menuOpen, confirm } = props;
    const { translator, link } = usePage();
    const { t, tn } = translator;
    const { kindsByCode, days, canChange } = useSnippetsEnv();
    const look = rowLook(row, active, kindsByCode);
    const href = link(withParam(linkTo(EDITOR), 'id', String(row.id)));
    const words = (
        <>
            <span class="rs-snippets__tile"><Icon name={look.icon} size={20} /></span>
            <span class="rs-snippets__words">
                <span class="rs-snippets__title">
                    <span class="rs-snippets__name">{row.name}</span>
                    {row.type === '' ? null : <Badge variant="code">{row.type}</Badge>}
                    {row.flags.bad === null ? null : <StatePill tone="error" filled>{row.flags.bad}</StatePill>}
                    {row.flags.warn === null ? null : <Badge variant="warn" icon="warning">{row.flags.warn}</Badge>}
                    {row.flags.note === null ? null : <Badge icon="info">{row.flags.note}</Badge>}
                </span>
                <span class="rs-snippets__where">{placementLine(row, kindsByCode, translator)}</span>
                <span class="rs-snippets__meta">{metaLine(row, translator, days)}</span>
            </span>
        </>
    );
    return (
        <li class="rs-snippets__item">
            <div
                class="rs-snippets__row"
                data-edge={look.edge}
                data-muted={look.muted ? 'true' : 'false'}
                data-marked={menuOpen || confirm !== null ? 'true' : 'false'}
            >
                {href === null
                    ? <div class="rs-snippets__open">{words}</div>
                    : <a class="rs-snippets__open" href={href}>{words}</a>}
                <span class="rs-snippets__controls">
                    {row.isBlock
                        ? <Badge variant="fixed" icon="link">{tn('snippets.blocks.usedIn', row.usedIn.length)}</Badge>
                        : canChange ? (
                            <Switch
                                checked={active}
                                onChange={props.onSwitch}
                                label={row.name}
                                size="small"
                                words={{ on: t('snippets.switch.on'), off: t('snippets.switch.off') }}
                            />
                        ) : null}
                    {canChange ? (
                        <MenuButton
                            label={t('snippets.menu.more', row.name)}
                            menuLabel={row.name}
                            open={menuOpen}
                            onOpenChange={props.onMenu}
                            actions={[
                                { key: 'duplicate', label: t('snippets.menu.duplicate'), icon: 'content_copy', onSelect: props.onDuplicate },
                                { key: 'export', label: t('snippets.menu.export'), icon: 'download', onSelect: props.onExport },
                                { key: 'delete', label: t('snippets.menu.delete'), icon: 'delete', tone: 'danger', onSelect: props.onAskDelete },
                            ]}
                        />
                    ) : null}
                </span>
            </div>
            {confirm === null ? null : (
                <div class="rs-snippets__confirm">
                    {questionOf(
                        deleteConfirmOf(row, confirm.includers),
                        { name: row.name, deleting: props.deleting, onDelete: props.onDelete, onKeep: props.onKeep },
                        translator,
                    )}
                </div>
            )}
        </li>
    );
}
