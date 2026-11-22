<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft\Factory;

use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Draft\DraftFactoryInterface;

/**
 * Creates the editor's values through the factories the platform generates for their contracts
 */
class DraftFactoryAdapter implements DraftFactoryInterface
{
    /**
     * @param DraftSnippetInterfaceFactory $drafts
     * @param DraftProblemInterfaceFactory $problems
     * @param DraftPreviewInterfaceFactory $previews
     * @param DraftSaveResultInterfaceFactory $results
     */
    public function __construct(
        private readonly DraftSnippetInterfaceFactory $drafts,
        private readonly DraftProblemInterfaceFactory $problems,
        private readonly DraftPreviewInterfaceFactory $previews,
        private readonly DraftSaveResultInterfaceFactory $results
    ) {
    }

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
        return $this->drafts->create([
            'id' => $id,
            'name' => $name,
            'template' => $template,
            'isBlock' => $isBlock,
            'active' => $active,
            'pageKinds' => $pageKinds,
            'conditions' => $conditions,
            'storeIds' => $storeIds,
        ]);
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
        return $this->problems->create([
            'part' => $part,
            'path' => $path,
            'code' => $code,
            'params' => $params,
            'offset' => $offset,
            'blocking' => $blocking,
        ]);
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
        return $this->previews->create([
            'state' => $state,
            'page' => $page,
            'output' => $output,
            'trace' => $trace,
            'mismatches' => $mismatches,
            'instead' => $instead,
            'check' => $check,
            'problems' => $problems,
            'conditionText' => $conditionText,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function saveResult(?SnippetInterface $snippet, array $problems = []): DraftSaveResultInterface
    {
        return $this->results->create(['snippet' => $snippet, 'problems' => $problems]);
    }
}
