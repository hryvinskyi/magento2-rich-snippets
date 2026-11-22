<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterface;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Api\StorefrontReaderInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\StorefrontPage;

/**
 * A storefront reader that answers every read with the HTML a test gives it, or fails as the test says, and records
 * the reads it was asked for
 */
class FixedStorefrontReader implements StorefrontReaderInterface
{
    /**
     * @var list<array{storeId: int, path: string}>
     */
    public array $reads = [];

    /**
     * @param string $html
     * @param StorefrontReadException|null $failure
     */
    public function __construct(
        private readonly string $html = '',
        private readonly ?StorefrontReadException $failure = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(int $storeId, string $path): StorefrontPageInterface
    {
        $this->reads[] = ['storeId' => $storeId, 'path' => $path];
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new StorefrontPage('http://shop.test' . $path, $path, 200, $this->html);
    }
}
