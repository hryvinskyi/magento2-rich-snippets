<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;

/**
 * A snippet's script block, as the output service built it
 */
class RenderedBlock implements RenderedBlockInterface
{
    /**
     * @param string $code
     * @param string $type
     * @param string $json
     */
    public function __construct(
        private readonly string $code,
        private readonly string $type,
        private readonly string $json
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getJson(): string
    {
        return $this->json;
    }
}
