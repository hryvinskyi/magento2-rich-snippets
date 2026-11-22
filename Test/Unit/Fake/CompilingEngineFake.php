<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\ScopeInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\JsonTemplate\Api\TraceInterface;
use Hryvinskyi\JsonTemplate\Engine\Data\CompiledTemplate;

/**
 * An engine whose compile refuses what a check refuses and which never evaluates or renders
 */
class CompilingEngineFake implements TemplateEngineInterface
{
    /**
     * The sources compiled, in order
     *
     * @var list<string>
     */
    public array $compiled = [];

    /**
     * @param \Closure(string):bool $compiles whether a source compiles
     */
    public function __construct(private readonly \Closure $compiles)
    {
    }

    /**
     * @inheritDoc
     */
    public function compile(string $source): CompiledTemplateInterface
    {
        $this->compiled[] = $source;
        if (!($this->compiles)($source)) {
            throw new \RuntimeException('The source does not compile.');
        }

        return new CompiledTemplate([], [], [], [], hash('xxh128', $source));
    }

    /**
     * @inheritDoc
     */
    public function evaluate(string $source, ScopeInterface $scope, ?TraceInterface $trace = null): mixed
    {
        throw new \LogicException('Not evaluated in these tests.');
    }

    /**
     * @inheritDoc
     */
    public function render(string $source, ScopeInterface $scope, int $flags = 0): ?string
    {
        throw new \LogicException('Not rendered in these tests.');
    }
}
