/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The draft's preview and save over the page's JSON client, and the readers of what they answer — the only file that
 * knows those shapes; nothing is cast, and the first wrong value throws a `ContractError` naming its path.
 *
 * - `preview` POSTs `{"snippet", "sample", "store"}` with the read deadline and answers
 *   `{"state", "sample", "output", "trace", "message", "checks", "problems"}`; a preview whose sample the store view
 *   does not have (`sample` null) is shown for the sample asked for.
 * - `save` POSTs `{"snippet", "updatedAt", "idempotencyKey"}` and answers `{"snippet", "problems"}` — the snippet as
 *   stored, or null with the problems that stopped the save — or, with the status 409, `{"conflict": {"message"}}`
 *   when someone else saved the snippet after the version the draft was made from.
 *
 * A draft is sent as the server reads one: `{"id", "name", "template", "isBlock", "active", "pageKinds",
 * "conditions", "storeIds"}`.
 */
import { at, bool, isRecord, items, nullable, oneOf, READ_DEADLINE_MS, record, text, type JsonClient } from '@hryvinskyi/admin-ui';
import type { DraftGateway, SaveAnswer } from '../application/ports.ts';
import type { CheckAction, CheckRow, CheckTone, Draft, DraftProblem, PreviewResult, PreviewState, Sample } from '../domain/model.ts';
import type { EditorEndpointName } from './editor-document.ts';
import { parseDraft, parseSample } from './editor-data.ts';

const CONFLICT = 409;
const STATES: readonly PreviewState[] = ['output', 'no_type', 'invalid', 'conditions_not_met', 'empty'];
const TONES: readonly CheckTone[] = ['bad', 'warn', 'note', 'ok'];

export const draftBody = (draft: Draft): Readonly<Record<string, unknown>> => ({
    id: draft.id,
    name: draft.name,
    template: draft.template,
    isBlock: draft.isBlock,
    active: draft.active,
    pageKinds: draft.pageKinds,
    conditions: draft.conditions,
    storeIds: draft.storeIds,
});

function action(value: unknown, path: string): CheckAction {
    const fields = record(value, path);
    return {
        label: text(fields['label'], at(path, 'label')),
        icon: text(fields['icon'], at(path, 'icon')),
        path: nullable(fields['path'], at(path, 'path'), text),
        value: nullable(fields['value'], at(path, 'value'), text),
        before: items(fields['before'], at(path, 'before'), text),
    };
}

function checkRow(value: unknown, path: string): CheckRow {
    const fields = record(value, path);
    return {
        tone: oneOf(TONES, fields['tone'], at(path, 'tone')),
        title: text(fields['title'], at(path, 'title')),
        text: text(fields['text'], at(path, 'text')),
        action: nullable(fields['action'], at(path, 'action'), action),
    };
}

function message(value: unknown, path: string): { readonly title: string; readonly text: string } {
    const fields = record(value, path);
    return { title: text(fields['title'], at(path, 'title')), text: text(fields['text'], at(path, 'text')) };
}

/** The preview the server answered, for the sample asked for when the server lists no sample of its own. */
export function parsePreview(value: unknown, path: string, asked: Sample): PreviewResult {
    const fields = record(value, path);
    return {
        sample: nullable(fields['sample'], at(path, 'sample'), parseSample) ?? asked,
        state: oneOf(STATES, fields['state'], at(path, 'state')),
        output: nullable(fields['output'], at(path, 'output'), record),
        message: nullable(fields['message'], at(path, 'message'), message),
        checks: items(fields['checks'], at(path, 'checks'), checkRow),
    };
}

function problem(value: unknown, path: string): DraftProblem {
    const fields = record(value, path);
    return {
        part: text(fields['part'], at(path, 'part')),
        code: text(fields['code'], at(path, 'code')),
        message: text(fields['message'], at(path, 'message')),
        blocking: bool(fields['blocking'], at(path, 'blocking')),
    };
}

/** What a save answered: a conflict, the snippet as stored, or the problems that stopped it. */
export function parseSaveAnswer(value: unknown, path: string): SaveAnswer {
    const fields = record(value, path);
    if (isRecord(fields['conflict'])) {
        const conflict = record(fields['conflict'], at(path, 'conflict'));
        return { kind: 'conflict', message: text(conflict['message'], at(at(path, 'conflict'), 'message')) };
    }
    const snippet = nullable(fields['snippet'], at(path, 'snippet'), parseDraft);
    return snippet === null
        ? { kind: 'refused', problems: items(fields['problems'], at(path, 'problems'), problem) }
        : { kind: 'saved', draft: snippet };
}

export function httpDraftGateway(client: JsonClient, url: (name: EditorEndpointName) => string): DraftGateway {
    return Object.freeze({
        async preview(draft: Draft, sample: Sample, store: number | null, signal: AbortSignal): Promise<PreviewResult> {
            const answer = await client.post(
                url('preview'),
                { snippet: draftBody(draft), sample: sample.ref, store },
                { signal, timeoutMs: READ_DEADLINE_MS },
            );
            return parsePreview(answer, '$', sample);
        },
        async save(draft: Draft, updatedAt: string | null, idempotencyKey: string | null, signal: AbortSignal): Promise<SaveAnswer> {
            const answer = await client.post(
                url('save'),
                { snippet: draftBody(draft), updatedAt, idempotencyKey },
                { signal, answered: [CONFLICT] },
            );
            return parseSaveAnswer(answer, '$');
        },
    });
}
