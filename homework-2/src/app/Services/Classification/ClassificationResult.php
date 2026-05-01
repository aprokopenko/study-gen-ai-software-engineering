<?php

declare(strict_types=1);

namespace App\Services\Classification;

readonly class ClassificationResult
{
    public function __construct(
        public float   $confidence,
        public string  $reasoning,
        public array   $keywords,
    ) {}
}
