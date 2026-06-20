<?php

declare(strict_types=1);

namespace App;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;

class LoremCapabilities
{
    #[McpResource(uri: 'file://lorem-ipsum.md', name: 'lorem_ipsum', mimeType: 'text/plain')]
    public function loremResource(int $wordCount = 30): string
    {
        return $this->firstWords($wordCount);
    }

    #[McpTool(name: 'read')]
    public function read(int $wordCount = 30): string
    {
        return $this->loremResource($wordCount);
    }

    private function firstWords(int $n): string
    {
        $text = file_get_contents(__DIR__ . '/../lorem-ipsum.md');
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($n <= 0) {
            $n = 30;
        }

        $slice = array_slice($words, 0, min($n, count($words)));

        return implode(' ', $slice);
    }
}
