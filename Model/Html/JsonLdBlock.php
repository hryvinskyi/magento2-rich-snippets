<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Html;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;

/**
 * A JSON-LD block found in a page
 */
class JsonLdBlock implements JsonLdBlockInterface
{
    /**
     * @param string|null $code
     * @param string $json
     * @param array<mixed>|null $value
     */
    public function __construct(
        private readonly ?string $code,
        private readonly string $json,
        private readonly ?array $value
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getJson(): string
    {
        return $this->json;
    }

    /**
     * @inheritDoc
     */
    public function getValue(): ?array
    {
        return $this->value;
    }
}
