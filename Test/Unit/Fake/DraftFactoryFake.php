<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Draft\DraftFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Draft\DraftPreview;
use Hryvinskyi\RichSnippets\Model\Draft\DraftProblem;
use Hryvinskyi\RichSnippets\Model\Draft\DraftSaveResult;
use Hryvinskyi\RichSnippets\Model\Draft\DraftSnippet;

/**
 * Creates the editor's values directly, as the generated factories do in the container
 */
class DraftFactoryFake implements DraftFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function draft(
        ?int $id,
        string $name,
        string $template,
        bool $isBlock,
        bool $active,
        array $pageKinds,
        string $conditions,
        array $storeIds
    ): DraftSnippetInterface {
        return new DraftSnippet($id, $name, $template, $isBlock, $active, $pageKinds, $conditions, $storeIds);
    }

    /**
     * @inheritDoc
     */
    public function problem(
        string $part,
        string $path,
        string $code,
        array $params = [],
        ?int $offset = null,
        bool $blocking = true
    ): DraftProblemInterface {
        return new DraftProblem($part, $path, $code, $params, $offset, $blocking);
    }

    /**
     * @inheritDoc
     */
    public function preview(
        PreviewState $state,
        PageEntityInterface $page,
        ?array $output = null,
        array $trace = [],
        array $mismatches = [],
        array $instead = [],
        ?OutputCheckInterface $check = null,
        array $problems = [],
        string $conditionText = ''
    ): DraftPreviewInterface {
        return new DraftPreview(
            $state,
            $page,
            $output,
            $trace,
            $mismatches,
            $instead,
            $check,
            $problems,
            $conditionText
        );
    }

    /**
     * @inheritDoc
     */
    public function saveResult(?SnippetInterface $snippet, array $problems = []): DraftSaveResultInterface
    {
        return new DraftSaveResult($snippet, $problems);
    }
}
