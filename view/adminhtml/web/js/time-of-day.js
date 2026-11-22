/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Whether a text is a time of day as the catalog check's start time takes it: hours and minutes on the 24-hour
 * clock, two digits each, separated by a colon. The server refuses the same texts, so this only answers earlier.
 */
define([], function () {
    'use strict';

    var TIME_OF_DAY = /^([01]\d|2[0-3]):[0-5]\d$/;

    return Object.freeze({
        /**
         * @param {unknown} value
         * @returns {boolean}
         */
        isTimeOfDay: function (value) {
            return typeof value === 'string' && TIME_OF_DAY.test(value.trim());
        }
    });
});
