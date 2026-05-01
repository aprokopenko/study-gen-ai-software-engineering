<?php

declare(strict_types=1);

namespace App\Services;

class ImportSummary
{
    /** @param list<array{row:int,field:string,message:string,raw:array}> $errors */
    public function __construct(
        public readonly int   $total,
        public readonly int   $successful,
        public readonly int   $failed,
        public readonly array $errors,
    ) {}

    public function toArray(): array
    {
        return [
            'total'      => $this->total,
            'successful' => $this->successful,
            'failed'     => $this->failed,
            'errors'     => $this->errors,
        ];
    }
}
