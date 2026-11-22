<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\QuestionSuggesterInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterPoolInterface;

/**
 * The suggesters of library questions, as di.xml lists them by code
 */
class QuestionSuggesterPool implements QuestionSuggesterPoolInterface
{
    /**
     * @param array<string,QuestionSuggesterInterface> $suggesters by code
     */
    public function __construct(private readonly array $suggesters = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function has(string $code): bool
    {
        return isset($this->suggesters[$code]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): QuestionSuggesterInterface
    {
        return $this->suggesters[$code]
            ?? throw new \OutOfBoundsException(sprintf('No question suggester has the code "%s".', $code));
    }
}
