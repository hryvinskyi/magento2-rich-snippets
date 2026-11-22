<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterface;

/**
 * A storefront page as it was read
 */
class StorefrontPage implements StorefrontPageInterface
{
    /**
     * @param string $url
     * @param string $path
     * @param int $status
     * @param string $body
     * @throws \InvalidArgumentException when the path does not start with `/` or the status is not a success
     */
    public function __construct(
        private readonly string $url,
        private readonly string $path,
        private readonly int $status,
        private readonly string $body
    ) {
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('A storefront page\'s path starts with "/".');
        }
        if ($status < 200 || $status > 299) {
            throw new \InvalidArgumentException(sprintf('A page read with status %d was not read.', $status));
        }
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        return $this->body;
    }
}
