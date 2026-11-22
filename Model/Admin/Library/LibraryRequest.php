<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;

/**
 * Reads what the Library page's requests carry, refusing a value of the wrong shape with the name of the value as the
 * reason (answered 400)
 *
 * A template is `template=<code>` in the query or `"template": code` in a body; the answers are `"answers"`, an object
 * of answer keys by question code; a write that creates carries `"idempotencyKey"`.
 */
class LibraryRequest
{
    public const TEMPLATE = 'template';
    public const ANSWERS = 'answers';
    public const IDEMPOTENCY_KEY = 'idempotencyKey';

    /**
     * The code of the template the request is about
     *
     * @param array<mixed> $values the query's parameters or the body's fields
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function template(array $values): string
    {
        $template = $values[self::TEMPLATE] ?? null;
        if (!is_string($template) || $template === '') {
            throw new InvalidWizardRequestException(self::TEMPLATE, 'The request names no library template.');
        }

        return $template;
    }

    /**
     * The answer chosen for each question; a question left out takes its suggested answer
     *
     * @param array<mixed> $values
     * @return array<string, string> answer keys by question code
     * @throws InvalidWizardRequestException when they are not an object of answer keys
     */
    public function answers(array $values): array
    {
        $given = $values[self::ANSWERS] ?? [];
        if (!is_array($given) || ($given !== [] && array_is_list($given))) {
            throw new InvalidWizardRequestException(self::ANSWERS, 'The answers are not an object.');
        }
        $answers = [];
        foreach ($given as $question => $answer) {
            if (!is_string($answer) || $answer === '') {
                throw new InvalidWizardRequestException(self::ANSWERS, 'An answer is not the key of an answer.');
            }
            $answers[(string) $question] = $answer;
        }

        return $answers;
    }

    /**
     * The idempotency key of a write that creates
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function idempotencyKey(array $values): string
    {
        $key = $values[self::IDEMPOTENCY_KEY] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidWizardRequestException('idempotency_key', 'The request carries no idempotency key.');
        }

        return $key;
    }
}
