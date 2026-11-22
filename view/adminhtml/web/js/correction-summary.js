/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the settings page says when Save Config finds fields of a section that need a correction: which fields they
 * are, each once, and whether the summary speaks of one field or of several. It holds no wording; the page chooses
 * the sentence for the form it is given.
 */
define([], function () {
    'use strict';

    return Object.freeze({
        /**
         * The IDs of a section's fields among the IDs of the fields with an error, each once, in their order. A
         * field of a section has an ID that starts with the section's ID and an underscore.
         *
         * @param {unknown} ids
         * @param {string} sectionId
         * @returns {string[]}
         */
        fieldsInSection: function (ids, sectionId) {
            var prefix = sectionId + '_',
                seen = Object.create(null),
                fields = [];

            if (!Array.isArray(ids)) {
                return fields;
            }
            ids.forEach(function (id) {
                if (typeof id === 'string' && id.indexOf(prefix) === 0 && !seen[id]) {
                    seen[id] = true;
                    fields.push(id);
                }
            });

            return fields;
        },

        /**
         * The form of the summary for a number of fields: none for no field, `one` for one, `many` with the count
         * for more
         *
         * @param {number} count
         * @returns {{form: 'one'|'many', count: number}|null}
         */
        summary: function (count) {
            if (!Number.isInteger(count) || count < 1) {
                return null;
            }

            return {form: count === 1 ? 'one' : 'many', count: count};
        }
    });
});
