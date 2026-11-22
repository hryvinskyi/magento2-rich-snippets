<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Plugin;

use Hryvinskyi\RichSnippets\Model\Storefront\MicrodataRemover;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupDecision;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Takes the theme's schema.org microdata items that the page's snippets replace out of a finished HTML page
 *
 * It runs once the result has rendered into the response and before the built-in page cache stores the response, so
 * the cached copy is the cleaned one; a page served from the cache is not rendered again and needs nothing. Which
 * items go was decided while the page's snippets were written, so nothing is evaluated here; the page's other items
 * stay. A response that is not HTML is left alone.
 */
class RemoveThemeMicrodata
{
    private const CONTENT_TYPE = 'Content-Type';
    private const HTML_MEDIA_TYPE = 'text/html';

    /**
     * @param ThemeMarkupDecision $decision
     * @param MicrodataRemover $remover
     */
    public function __construct(
        private readonly ThemeMarkupDecision $decision,
        private readonly MicrodataRemover $remover
    ) {
    }

    /**
     * Clean the rendered page of the theme's items its snippets replace
     *
     * @param ResultInterface $subject the intercepted result; the signature of an after plugin requires it
     * @param ResultInterface $result
     * @param ResponseInterface $response
     * @return ResultInterface
     */
    public function afterRenderResult(
        ResultInterface $subject,
        ResultInterface $result,
        ResponseInterface $response
    ): ResultInterface {
        $types = $this->decision->replacedTypes();
        if ($types === [] || !$response instanceof HttpResponse || !$this->isHtml($response)) {
            return $result;
        }
        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setContent($this->remover->strip($content, $types));
        }

        return $result;
    }

    /**
     * Whether the response is an HTML page: it says so, or it names no media type
     *
     * @param HttpResponse $response
     * @return bool
     */
    private function isHtml(HttpResponse $response): bool
    {
        $header = $response->getHeader(self::CONTENT_TYPE);
        if (!is_object($header)) {
            return true;
        }

        return stripos($header->getFieldValue(), self::HTML_MEDIA_TYPE) !== false;
    }
}
