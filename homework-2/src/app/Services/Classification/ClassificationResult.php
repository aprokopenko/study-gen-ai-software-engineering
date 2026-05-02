<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;

readonly class ClassificationResult
{
    public function __construct(
        public Category $suggestedCategory,
        public Priority $suggestedPriority,
        public float    $confidence,
        public string   $reasoning,
        public array    $keywords,
    ) {}
}