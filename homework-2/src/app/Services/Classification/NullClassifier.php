<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Entities\Ticket;
use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;

class NullClassifier implements ClassifierInterface
{
    public function classify(Ticket $ticket): ClassificationResult
    {
        return new ClassificationResult(
            suggestedCategory: Category::Other,
            suggestedPriority: Priority::Medium,
            confidence:        0.0,
            reasoning:         '',
            keywords:          [],
        );
    }
}