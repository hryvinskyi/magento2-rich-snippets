<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Reads a JSON object a module ships in a directory under its `etc`
 *
 * The file is named by the module and a plain file name, never a path, so a definition cannot reach outside the
 * module's directory.
 */
class ModuleJsonFile
{
    private const FILE_NAME_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9_-]*\.json\z/';

    /**
     * @param Dir $moduleDirs
     * @param DriverInterface $files
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly Dir $moduleDirs,
        private readonly DriverInterface $files,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * The file's JSON object, decoded
     *
     * @param string $moduleName the module that ships the file (`Vendor_Module`)
     * @param string $directory the directory under the module's `etc` (`rich_snippets/library`)
     * @param string $fileName the file's name in that directory (`product.json`)
     * @return array<mixed>
     * @throws \UnexpectedValueException when the module is unknown, the name is not a plain JSON file name, or the
     *     file cannot be read, is not JSON or holds no JSON object
     */
    public function read(string $moduleName, string $directory, string $fileName): array
    {
        $path = $this->path($moduleName, $directory, $fileName);
        try {
            $definition = $this->json->unserialize($this->files->fileGetContents($path));
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException(
                sprintf('The file "%s" cannot be read as JSON: %s', $path, $e->getMessage()),
                0,
                $e
            );
        }
        if (!is_array($definition) || array_is_list($definition)) {
            throw new \UnexpectedValueException(sprintf('The file "%s" does not hold a JSON object.', $path));
        }

        return $definition;
    }

    /**
     * The file's absolute path
     *
     * @param string $moduleName
     * @param string $directory
     * @param string $fileName
     * @return string
     * @throws \UnexpectedValueException
     */
    private function path(string $moduleName, string $directory, string $fileName): string
    {
        if (preg_match(self::FILE_NAME_PATTERN, $fileName) !== 1) {
            throw new \UnexpectedValueException(sprintf('"%s" is not a plain JSON file name.', $fileName));
        }
        try {
            $etc = $this->moduleDirs->getDir($moduleName, Dir::MODULE_ETC_DIR);
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException(
                sprintf('The module "%s" of the file "%s" is not known.', $moduleName, $fileName),
                0,
                $e
            );
        }

        return $etc . '/' . $directory . '/' . $fileName;
    }
}
