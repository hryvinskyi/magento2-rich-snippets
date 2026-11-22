/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Registers the form rule `validate-rich-snippets-time` with the platform's validation, so a field carrying the rule
 * is marked with the translated message before the form is sent. The predicate lives in its own module; this one
 * only binds it and chooses the wording.
 */
define([
    'jquery',
    'mage/translate',
    'Hryvinskyi_RichSnippets/js/time-of-day'
], function ($, $t, timeOfDay) {
    'use strict';

    return function (validation) {
        $.validator.addMethod(
            'validate-rich-snippets-time',
            function (value) {
                return timeOfDay.isTimeOfDay(value);
            },
            $t('Enter a time such as 03:00 or 23:30.')
        );

        return validation;
    };
});
