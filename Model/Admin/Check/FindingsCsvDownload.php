<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Answers the pages behind a finding as a CSV file to save, under its name by `Content-Disposition: attachment`
 *
 * A refusal or a failure is answered the way every endpoint answers one, as JSON without the attachment header.
 */
class FindingsCsvDownload
{
    private const ENDPOINT = 'rich_snippets.test.findings_csv';

    /**
     * @param JsonAnswerInterface $jsonAnswer
     * @param CheckAnswers $answers
     * @param RawFactory $raw
     */
    public function __construct(
        private readonly JsonAnswerInterface $jsonAnswer,
        private readonly CheckAnswers $answers,
        private readonly RawFactory $raw
    ) {
    }

    /**
     * The file the query asks for
     *
     * @param array<mixed> $query
     * @return ResultInterface
     */
    public function answer(array $query): ResultInterface
    {
        $file = null;
        $json = $this->jsonAnswer->answer(
            self::ENDPOINT,
            function () use ($query, &$file): array {
                $file = $this->answers->csv($query);

                return [];
            }
        );
        if (!$file instanceof FindingsCsvInterface) {
            return $json;
        }
        $result = $this->raw->create();
        $result->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $result->setHeader(
            'Content-Disposition',
            sprintf('attachment; filename="%s"', str_replace(['"', '\\'], '', $file->getFileName())),
            true
        );
        $result->setContents($file->getContent());

        return $result;
    }
}
