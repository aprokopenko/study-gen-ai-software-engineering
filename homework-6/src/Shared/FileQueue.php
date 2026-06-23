<?php

declare(strict_types=1);

namespace BankingPipeline\Shared;

use RuntimeException;

/**
 * Atomic file-based message queue for the pipeline hand-off directories.
 *
 * Stages never call each other directly — each reads from one shared directory
 * and writes its result to the next, like a conveyor belt.
 *
 * Directory layout (relative to $baseDir):
 *   input/       ← initial messages dropped here
 *   processing/  ← a stage moves a message here while working on it
 *   output/      ← a stage writes its result here for the next stage
 *   results/     ← final outcome (settled or rejected) lands here
 */
final class FileQueue
{
    public const DIR_INPUT      = 'input';
    public const DIR_PROCESSING = 'processing';
    public const DIR_OUTPUT     = 'output';
    public const DIR_RESULTS    = 'results';

    /** Valid queue directory names. */
    private const VALID_DIRS = [
        self::DIR_INPUT,
        self::DIR_PROCESSING,
        self::DIR_OUTPUT,
        self::DIR_RESULTS,
    ];

    public function __construct(private readonly string $baseDir) {}

    /**
     * Ensure all queue subdirectories exist.
     */
    public function initialize(): void
    {
        foreach (self::VALID_DIRS as $dir) {
            $path = $this->path($dir);
            if (!is_dir($path) && !mkdir($path, 0755, recursive: true)) {
                throw new RuntimeException("Cannot create queue directory: {$path}");
            }
        }
    }

    /**
     * Write an envelope to the given queue directory as a JSON file named after
     * the transaction ID (or message ID as fallback).
     *
     * @return string The full path of the written file.
     */
    public function write(string $dir, Envelope $envelope): string
    {
        $this->assertValidDir($dir);

        // Name the file after the transaction_id if present, otherwise message_id
        $txnId    = $envelope->data['transaction_id'] ?? $envelope->messageId;
        $filename = $this->sanitizeFilename($txnId) . '.json';
        $destPath = $this->path($dir) . DIRECTORY_SEPARATOR . $filename;

        $this->atomicWrite($destPath, $envelope->toJson());

        return $destPath;
    }

    /**
     * Atomically move a message file from one queue directory to another.
     *
     * The rename() call is atomic on the same filesystem (POSIX guarantee).
     *
     * @throws RuntimeException if the source file does not exist.
     * @return string The new full path of the file.
     */
    public function move(string $filename, string $fromDir, string $toDir): string
    {
        $this->assertValidDir($fromDir);
        $this->assertValidDir($toDir);

        $srcPath  = $this->path($fromDir) . DIRECTORY_SEPARATOR . $filename;
        $destPath = $this->path($toDir) . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($srcPath)) {
            throw new RuntimeException("File not found in queue '{$fromDir}': {$filename}");
        }

        if (!rename($srcPath, $destPath)) {
            throw new RuntimeException(
                "Failed to move '{$filename}' from '{$fromDir}' to '{$toDir}'."
            );
        }

        return $destPath;
    }

    /**
     * Read and parse an envelope from a JSON file in the given queue directory.
     *
     * @throws RuntimeException if the file is not found.
     * @throws \InvalidArgumentException if the JSON is malformed or envelope fields are missing.
     */
    public function read(string $filename, string $dir): Envelope
    {
        $this->assertValidDir($dir);

        $path = $this->path($dir) . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($path)) {
            throw new RuntimeException("File not found in queue '{$dir}': {$filename}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot read file: {$path}");
        }

        return Envelope::fromJson($json);
    }

    /**
     * List all filenames (basenames only) in a queue directory.
     *
     * @return string[]
     */
    public function listFiles(string $dir): array
    {
        $this->assertValidDir($dir);

        $path  = $this->path($dir);
        $files = glob($path . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return [];
        }

        return array_map('basename', $files);
    }

    /**
     * Clear all JSON files from a queue directory.
     */
    public function clear(string $dir): void
    {
        $this->assertValidDir($dir);

        foreach ($this->listFiles($dir) as $filename) {
            $path = $this->path($dir) . DIRECTORY_SEPARATOR . $filename;
            @unlink($path);
        }
    }

    /**
     * Return the absolute path to a queue subdirectory.
     */
    private function path(string $dir): string
    {
        return rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dir;
    }

    /**
     * Write content to a file atomically via a temporary file + rename.
     */
    private function atomicWrite(string $destPath, string $content): void
    {
        $tmpPath = $destPath . '.tmp.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmpPath, $content) === false) {
            throw new RuntimeException("Cannot write temporary file: {$tmpPath}");
        }

        if (!rename($tmpPath, $destPath)) {
            @unlink($tmpPath);
            throw new RuntimeException("Cannot atomically write file: {$destPath}");
        }
    }

    /**
     * Strip characters unsafe for filenames.
     */
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    }

    private function assertValidDir(string $dir): void
    {
        if (!in_array($dir, self::VALID_DIRS, strict: true)) {
            throw new \InvalidArgumentException(
                "Invalid queue directory '{$dir}'. Valid: " . implode(', ', self::VALID_DIRS)
            );
        }
    }
}
