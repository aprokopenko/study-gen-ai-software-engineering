# Test Coverage Report

**PHPUnit 11** · PHP 8.5 · 115 tests, 376 assertions — all passing

## Summary

| Metric   | Covered | Total | Ratio      |
|----------|---------|-------|------------|
| Lines    | 510     | 552   | **92.39%** |
| Methods  | 63      | 75    | **84.00%** |
| Classes  | 17      | 27    | **62.96%** |

## Per-Class Breakdown

| Class | Methods | Lines |
|---|---|---|
| `App\Controllers\AbstractController` | 100% (1/1) | 100% (4/4) |
| `App\Controllers\HealthController` | 100% (1/1) | 100% (4/4) |
| `App\Controllers\TicketsController` | 87.50% (7/8) | 94.59% (35/37) |
| `App\Entities\Ticket` | 100% (5/5) | 100% (87/87) |
| `App\Entities\TicketMetadata` | 100% (3/3) | 100% (11/11) |
| `App\Filters\TicketFilter` | 100% (2/2) | 100% (13/13) |
| `App\Http\ErrorHandler` | 100% (1/1) | 100% (3/3) |
| `App\Http\ErrorRenderer` | 100% (1/1) | 100% (8/8) |
| `App\Parsers\CsvTicketParser` | 50.00% (1/2) | 90.00% (18/20) |
| `App\Parsers\JsonTicketParser` | 0.00% (0/1) | 92.31% (12/13) |
| `App\Parsers\ParserRegistry` | 100% (2/2) | 100% (14/14) |
| `App\Parsers\XmlTicketParser` | 100% (2/2) | 100% (28/28) |
| `App\Repositories\TicketLogRepository` | 100% (2/2) | 100% (3/3) |
| `App\Repositories\TicketRepository` | 88.89% (8/9) | 91.18% (31/34) |
| `App\Services\Classification\ClassificationLogger` | 100% (2/2) | 100% (13/13) |
| `App\Services\Classification\ClassificationResult` | 100% (1/1) | 100% (1/1) |
| `App\Services\Classification\KeywordClassifier` | 33.33% (1/3) | 72.22% (26/36) |
| `App\Services\Clock\SystemClock` | 100% (1/1) | 100% (1/1) |
| `App\Services\ContainerFactory` | 100% (2/2) | 100% (6/6) |
| `App\Services\Database` | 66.67% (2/3) | 75.00% (6/8) |
| `App\Services\Ids\UuidGenerator` | 100% (1/1) | 100% (1/1) |
| `App\Services\ImportService` | 50.00% (1/2) | 81.58% (31/38) |
| `App\Services\ImportSummary` | 100% (2/2) | 100% (7/7) |
| `App\Services\TicketService` | 87.50% (7/8) | 98.85% (86/87) |
| `App\Validation\TicketValidator` | 71.43% (5/7) | 87.80% (36/41) |
| `App\Validation\ValidationException` | 100% (2/2) | 100% (2/2) |
