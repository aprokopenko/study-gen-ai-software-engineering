<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Entities\Ticket;

class NullClassifier implements ClassifierInterface
{
    public function classify(Ticket $ticket): ClassificationResult
    {
        return new ClassificationResult(confidence: 0.0, reasoning: '', keywords: []);
    }
}
