<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * Why the library refused a request
 *
 * @api
 */
enum LibraryReason: string
{
    /** No library template has the code; parameter `template` */
    case TemplateUnknown = 'template_unknown';

    /** The template asks no question with the code; parameters `template`, `question` */
    case QuestionUnknown = 'question_unknown';

    /** The question offers no answer with the key; parameters `question`, `answer` */
    case AnswerUnknown = 'answer_unknown';
}
