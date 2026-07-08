<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Entities\Ticket;
use App\Enums\Ticket\Category;
use App\Enums\Ticket\Priority;

class KeywordClassifier implements ClassifierInterface
{
    public function __construct(
        private readonly array $categoryKeywords,
        private readonly array $priorityKeywords,
    ) {}

    public function classify(Ticket $ticket): ClassificationResult
    {
        $haystack = strtolower($ticket->subject . ' ' . $ticket->description);

        [$bestCategory, $categoryMatches] = $this->bestMatch($this->categoryKeywords, $haystack);
        [$bestPriority, $priorityMatches] = $this->bestMatch($this->priorityKeywords, $haystack);

        $suggestedCategory = $bestCategory !== null ? Category::from($bestCategory) : Category::Other;
        $suggestedPriority = $bestPriority !== null ? Priority::from($bestPriority) : Priority::Medium;

        $allKeywords = array_values(array_unique(array_merge($categoryMatches, $priorityMatches)));
        $confidence = min(1.0, count($allKeywords) / 3);

        if (empty($allKeywords)) {
            $reasoning = 'No keywords matched; defaulted to other/medium';
        } else {
            $parts = [];
            if (!empty($categoryMatches)) {
                $parts[] = "Matched {$bestCategory} via [" . implode(', ', $categoryMatches) . ']';
            }
            if (!empty($priorityMatches)) {
                $parts[] = "priority={$bestPriority} via [" . implode(', ', $priorityMatches) . ']';
            }
            $reasoning = implode('; ', $parts);
        }

        return new ClassificationResult(
            suggestedCategory: $suggestedCategory,
            suggestedPriority: $suggestedPriority,
            confidence:        $confidence,
            reasoning:         $reasoning,
            keywords:          $allKeywords,
        );
    }

    /** @return array{?string, string[]} */
    private function bestMatch(array $map, string $haystack): array
    {
        $bestKey = null;
        $bestCount = 0;
        $bestMatches = [];

        foreach ($map as $key => $keywords) {
            $matches = [];
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $matches[] = $keyword;
                }
            }
            if (count($matches) > $bestCount) {
                $bestCount = count($matches);
                $bestKey = $key;
                $bestMatches = $matches;
            }
        }

        return [$bestKey, $bestMatches];
    }
}