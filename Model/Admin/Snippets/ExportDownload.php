<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers an export as a JSON file to save: the export document as the body, offered under the export's file name
 * by `Content-Disposition: attachment`
 *
 * A refusal or a failure is answered the way every endpoint answers one, without the attachment header.
 */
class ExportDownload
{
    private const ENDPOINT = 'rich_snippets.snippets.export';
    private const DISPOSITION = 'Content-Disposition';

    /**
     * @param JsonAnswerInterface $jsonAnswer
     * @param SnippetTransfers $transfers
     */
    public function __construct(
        private readonly JsonAnswerInterface $jsonAnswer,
        private readonly SnippetTransfers $transfers
    ) {
    }

    /**
     * The export the query asks for, as a file
     *
     * @param array<mixed> $params
     * @return Json
     */
    public function answer(array $params): Json
    {
        $file = null;
        $json = $this->jsonAnswer->answer(
            self::ENDPOINT,
            function () use ($params, &$file): array {
                $file = $this->transfers->export($params);

                return $file->getDocument();
            }
        );
        if ($file instanceof SnippetExportFileInterface) {
            $json->setHeader(
                self::DISPOSITION,
                sprintf('attachment; filename="%s"', str_replace(['"', '\\'], '', $file->getFileName())),
                true
            );
        }

        return $json;
    }
}
