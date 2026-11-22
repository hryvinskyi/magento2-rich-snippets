/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * On the settings page of Rich Snippets, a Save Config that the browser's checks stop says so above the form: "The
 * configuration was not saved. One field needs a correction; it is marked below." (or how many fields need one),
 * and every fieldset holding such a field is opened. The platform's validation only marks each field; the server
 * reports a refused save with one message for the whole form. Which fields count and which sentence applies is the
 * pure module's; this one only binds it to the form, chooses the wording and writes the page.
 */
define([
    'jquery',
    'mage/translate',
    'Hryvinskyi_RichSnippets/js/correction-summary'
], function ($, $t, correctionSummary) {
    'use strict';

    var SECTION = 'hryvinskyi_rich_snippets',
        MESSAGES = 'messages',
        FIELDSET = '.section-config',
        OPEN = 'active',
        FIELDSET_HEAD = '.entry-edit-head a[id$="-head"]',
        SENTENCES = {
            one: function () {
                return $t('The configuration was not saved. One field needs a correction; it is marked below.');
            },
            many: function (count) {
                return $t('The configuration was not saved. %1 fields need a correction; they are marked below.')
                    .replace('%1', String(count));
            }
        };

    /**
     * Write the summary in place of the page's messages
     *
     * @param {Document} document
     * @param {string} text
     */
    function showSummary(document, text) {
        var holder = document.getElementById(MESSAGES),
            list,
            message,
            body;

        if (!holder) {
            return;
        }
        list = document.createElement('div');
        list.className = 'messages';
        message = document.createElement('div');
        message.className = 'message message-error error';
        body = document.createElement('div');
        body.setAttribute('data-ui-id', 'messages-message-error');
        body.textContent = text;
        message.appendChild(body);
        list.appendChild(message);
        holder.replaceChildren(list);
    }

    /**
     * Open each closed fieldset that holds one of the fields, the way a click on its title does
     *
     * @param {Document} document
     * @param {string[]} fieldIds
     */
    function openFieldsets(document, fieldIds) {
        fieldIds.forEach(function (id) {
            var field = document.getElementById(id),
                fieldset = field ? field.closest(FIELDSET) : null,
                head = fieldset && !fieldset.classList.contains(OPEN) ? fieldset.querySelector(FIELDSET_HEAD) : null;

            if (head) {
                head.click();
            }
        });
    }

    /**
     * Answer a Save Config that the checks stopped
     *
     * @param {Document} document
     * @param {{errorList?: Array<{element?: {id?: unknown}}>}} validator
     */
    function summarise(document, validator) {
        var errors = validator && Array.isArray(validator.errorList) ? validator.errorList : [],
            fields = correctionSummary.fieldsInSection(errors.map(function (error) {
                return error && error.element ? error.element.id : null;
            }), SECTION),
            summary = correctionSummary.summary(fields.length);

        if (summary === null) {
            return;
        }
        showSummary(document, SENTENCES[summary.form](summary.count));
        openFieldsets(document, fields);
    }

    return function (validation) {
        $.widget('mage.validation', validation, {
            /**
             * Listen for stopped saves on a form that holds the section's fields
             */
            _create: function () {
                var form = this.element;

                this._super();
                if (form.find('[id^="' + SECTION + '_"]').length === 0) {
                    return;
                }
                form.on('invalid-form.validate', function (event, validator) {
                    summarise(form[0].ownerDocument, validator);
                });
            }
        });

        return $.mage.validation;
    };
});
