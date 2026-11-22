<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Source;

use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplateSourceInterface;
use Hryvinskyi\RichSnippets\Model\Config\ModuleJsonFile;

/**
 * A library template definition shipped as a JSON file in a module: `etc/rich_snippets/library/<file>` of that module
 *
 * Configured per template in di.xml with the module's name and the file's name, so a module adds a template without
 * code.
 */
class ModuleLibraryFile implements LibraryTemplateSourceInterface
{
    public const DIRECTORY = 'rich_snippets/library';

    /**
     * @param ModuleJsonFile $files
     * @param string $moduleName the module that ships the file (`Vendor_Module`)
     * @param string $fileName the file's name in the module's library directory (`product.json`)
     */
    public function __construct(
        private readonly ModuleJsonFile $files,
        private readonly string $moduleName,
        private readonly string $fileName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(): array
    {
        try {
            return $this->files->read($this->moduleName, self::DIRECTORY, $this->fileName);
        } catch (\UnexpectedValueException $e) {
            throw new MalformedLibraryTemplateException($e->getMessage(), 0, $e);
        }
    }
}
