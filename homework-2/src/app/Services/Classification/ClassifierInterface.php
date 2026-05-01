<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Entities\Ticket;

interface ClassifierInterface
{
    public function classify(Ticket $ticket): ClassificationResult;
}
