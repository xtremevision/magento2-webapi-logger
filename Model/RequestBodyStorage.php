<?php
/**
 * Copyright © OpenGento, All rights reserved.
 * See LICENSE bundled with this library for license details.
 */

declare(strict_types=1);

namespace Opengento\WebapiLogger\Model;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

use function bin2hex;
use function date;
use function ltrim;
use function random_bytes;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

class RequestBodyStorage
{
    private const STORAGE_PREFIX = '@disk:';
    private const STORAGE_DIR = 'webapi-logger/request-bodies';

    public function __construct(
        private Filesystem $filesystem,
        private LoggerInterface $logger
    ) {}

    public function store(string $requestBody): string
    {
        try {
            $relativePath = $this->buildRelativePath();
            $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR)->writeFile($relativePath, $requestBody);

            return self::STORAGE_PREFIX . $relativePath;
        } catch (Exception $exception) {
            $this->logger->error('Could not store request body on disk: ' . $exception->getMessage());

            return $requestBody;
        }
    }

    public function resolve(string $value): string
    {
        if (!$this->isDiskReference($value)) {
            return $value;
        }

        $relativePath = $this->getRelativePathFromReference($value);
        if ($relativePath === null) {
            return '';
        }

        try {
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            if (!$directory->isExist($relativePath)) {
                return '';
            }

            return $directory->readFile($relativePath);
        } catch (Exception $exception) {
            $this->logger->error('Could not read request body from disk: ' . $exception->getMessage());

            return '';
        }
    }

    public function isDiskReference(string $value): bool
    {
        return str_starts_with($value, self::STORAGE_PREFIX);
    }

    private function getRelativePathFromReference(string $value): ?string
    {
        if (!$this->isDiskReference($value)) {
            return null;
        }

        $relativePath = trim(substr($value, strlen(self::STORAGE_PREFIX)));
        if ($relativePath === '') {
            return null;
        }

        return ltrim($relativePath, '/');
    }

    private function buildRelativePath(): string
    {
        return self::STORAGE_DIR . '/' . date('Y/m/d') . '/' . bin2hex(random_bytes(16)) . '.json';
    }
}
