<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Http\JsonAnswerInterface;
use Hryvinskyi\AdminSetupWizard\Api\Http\JsonBodyInterface;
use Hryvinskyi\RichSnippets\Api\Exception\StaleSnippetException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;

/**
 * Answers the editor's save, with the status 409 when the snippet was saved by someone else after the version the
 * editor loaded
 *
 * Every other outcome is answered as the module's endpoints answer: the result with 200, a request of the wrong
 * shape with 400, a failure with 500.
 */
class SaveAnswer
{
    private const ENDPOINT = 'rich_snippets.snippets.save';
    private const CONFLICT = 409;

    /**
     * @param JsonAnswerInterface $jsonAnswer
     * @param JsonBodyInterface $jsonBody
     * @param EditorDocument $document
     */
    public function __construct(
        private readonly JsonAnswerInterface $jsonAnswer,
        private readonly JsonBodyInterface $jsonBody,
        private readonly EditorDocument $document
    ) {
    }

    /**
     * Save the draft the request carries and answer the outcome
     *
     * @param RequestInterface $request
     * @return Json
     */
    public function answer(RequestInterface $request): Json
    {
        $stale = false;
        $json = $this->jsonAnswer->answer(
            self::ENDPOINT,
            function () use ($request, &$stale): array {
                try {
                    return $this->document->save($this->jsonBody->read($request));
                } catch (StaleSnippetException $e) {
                    $stale = true;

                    return $this->document->conflict($e);
                }
            }
        );
        if ($stale) {
            $json->setHttpResponseCode(self::CONFLICT);
        }

        return $json;
    }
}
