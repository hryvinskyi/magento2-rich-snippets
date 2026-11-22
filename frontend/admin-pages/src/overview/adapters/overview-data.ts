/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads the Overview as the server prints it into the page and answers it from `overview/data`:
 * `{"store", "state", "badge", "status", "facts", "kinds", "features", "attention"}`. The only file that knows that
 * shape; nothing is cast, and the first wrong value throws a `ContractError` naming its path.
 */
import {
    at,
    bool,
    filledText,
    items,
    nullable,
    oneOf,
    record,
    screenLink,
    text,
    wholeNumber,
} from '@hryvinskyi/admin-ui';
import {
    ATTENTION_TONES,
    FEATURE_STATUSES,
    SETUP_STATES,
    STATUS_TONES,
    type Attention,
    type AttentionItem,
    type Badge,
    type EmptyNote,
    type Fact,
    type FeatureRow,
    type Features,
    type KindRow,
    type KindSnippet,
    type LabelledLink,
    type OverviewData,
    type Status,
} from '../domain/model.ts';

function labelledLink(value: unknown, path: string): LabelledLink {
    const fields = record(value, path);
    return { label: filledText(fields['label'], at(path, 'label')), link: screenLink(value, path) };
}

function badge(value: unknown, path: string): Badge {
    const fields = record(value, path);
    return { on: bool(fields['on'], at(path, 'on')), text: filledText(fields['text'], at(path, 'text')) };
}

function status(value: unknown, path: string): Status {
    const fields = record(value, path);
    return {
        tone: oneOf(STATUS_TONES, fields['tone'], at(path, 'tone')),
        word: filledText(fields['word'], at(path, 'word')),
        note: text(fields['note'], at(path, 'note')),
        action: nullable(fields['action'], at(path, 'action'), labelledLink),
    };
}

function fact(value: unknown, path: string): Fact {
    const fields = record(value, path);
    return {
        key: filledText(fields['key'], at(path, 'key')),
        label: filledText(fields['label'], at(path, 'label')),
        value: text(fields['value'], at(path, 'value')),
        warn: nullable(fields['tone'], at(path, 'tone'), (tone, where) => oneOf(['warn'], tone, where)) === 'warn',
    };
}

function kindSnippet(value: unknown, path: string): KindSnippet {
    const fields = record(value, path);
    return {
        id: wholeNumber(fields['id'], at(path, 'id')),
        code: filledText(fields['code'], at(path, 'code')),
        name: filledText(fields['name'], at(path, 'name')),
        on: bool(fields['on'], at(path, 'on')),
        sub: nullable(fields['sub'], at(path, 'sub'), text),
    };
}

function kindRow(value: unknown, path: string): KindRow {
    const fields = record(value, path);
    return {
        kind: filledText(fields['kind'], at(path, 'kind')),
        label: filledText(fields['label'], at(path, 'label')),
        icon: filledText(fields['icon'], at(path, 'icon')),
        pages: text(fields['pages'], at(path, 'pages')),
        see: nullable(fields['see'], at(path, 'see'), screenLink),
        empty: nullable(fields['empty'], at(path, 'empty'), text),
        snippets: items(fields['snippets'], at(path, 'snippets'), kindSnippet),
    };
}

function featureRow(value: unknown, path: string): FeatureRow {
    const fields = record(value, path);
    return {
        rule: filledText(fields['rule'], at(path, 'rule')),
        label: filledText(fields['label'], at(path, 'label')),
        on: text(fields['on'], at(path, 'on')),
        met: wholeNumber(fields['met'], at(path, 'met')),
        total: wholeNumber(fields['total'], at(path, 'total')),
        bar: bool(fields['bar'], at(path, 'bar')),
        text: text(fields['text'], at(path, 'text')),
        status: oneOf(FEATURE_STATUSES, fields['status'], at(path, 'status')),
        statusLabel: filledText(fields['statusLabel'], at(path, 'statusLabel')),
    };
}

function emptyNote(value: unknown, path: string): EmptyNote {
    const fields = record(value, path);
    return { title: filledText(fields['title'], at(path, 'title')), text: text(fields['text'], at(path, 'text')) };
}

function features(value: unknown, path: string): Features {
    const fields = record(value, path);
    return {
        subtitle: text(fields['subtitle'], at(path, 'subtitle')),
        rows: items(fields['rows'], at(path, 'rows'), featureRow),
        footer: nullable(fields['footer'], at(path, 'footer'), text),
        empty: nullable(fields['empty'], at(path, 'empty'), emptyNote),
    };
}

function attentionItem(value: unknown, path: string): AttentionItem {
    const fields = record(value, path);
    return {
        key: filledText(fields['key'], at(path, 'key')),
        tone: oneOf(ATTENTION_TONES, fields['tone'], at(path, 'tone')),
        title: filledText(fields['title'], at(path, 'title')),
        description: text(fields['description'], at(path, 'description')),
        action: filledText(fields['action'], at(path, 'action')),
        link: screenLink(fields['target'], at(path, 'target')),
    };
}

function attention(value: unknown, path: string): Attention {
    const fields = record(value, path);
    return {
        count: wholeNumber(fields['count'], at(path, 'count')),
        items: items(fields['items'], at(path, 'items'), attentionItem),
        none: nullable(fields['none'], at(path, 'none'), text),
    };
}

/** The Overview for every store view or one; the first wrong value throws a `ContractError`. */
export function parseOverviewData(value: unknown, path: string): OverviewData {
    const fields = record(value, path);
    const kinds = record(fields['kinds'], at(path, 'kinds'));
    return {
        store: nullable(fields['store'], at(path, 'store'), wholeNumber),
        state: oneOf(SETUP_STATES, fields['state'], at(path, 'state')),
        badge: badge(fields['badge'], at(path, 'badge')),
        status: status(fields['status'], at(path, 'status')),
        facts: items(fields['facts'], at(path, 'facts'), fact),
        kinds: {
            subtitle: text(kinds['subtitle'], at(at(path, 'kinds'), 'subtitle')),
            rows: items(kinds['rows'], at(at(path, 'kinds'), 'rows'), kindRow),
        },
        features: features(fields['features'], at(path, 'features')),
        attention: attention(fields['attention'], at(path, 'attention')),
    };
}
