# Implementation Plan: Test Suite Generation

## Overview

Build a comprehensive PHPUnit 11 test suite for the Intelligent Customer Support System. Tasks are ordered: shared infrastructure first, then unit tests, then feature tests, then integration tests. All tests run via `make phpunit` inside Docker. The implementation language is PHP 8.5 with `declare(strict_types=1)` in every file.

## Tasks

- [x] 1. Set up shared test infrastructure
  - [x] 1.1 Enhance `AppTestCase` with `putJson()` and `delete()` helpers
    - Add `putJson(string $path, array $data): ResponseInterface` method following the same pattern as `postJson()` — build a PSR-7 PUT request with JSON-encoded body and `Content-Type: application/json` header
    - Add `delete(string $path): ResponseInterface` method — build a PSR-7 DELETE request and pass through `$this->app->handle()`
    - File: `src/tests/Concerns/AppTestCase.php`
    - _Requirements: 1.7, 1.8, 1.9, 1.10_

  - [x] 1.2 Create `TicketDataBuilder` trait
    - Create `src/tests/Traits/TicketDataBuilder.php` with three factory methods:
      - `validTicketData(array $overrides = []): array` — returns a complete valid ticket payload for POST /tickets (customer_id, customer_email, customer_name, subject, description, category, priority, status, tags, metadata)
      - `validTicketRow(array $overrides = []): array` — returns a valid database row array for `Ticket::fromRow()` with all columns including metadata_source, metadata_browser, metadata_device_type, classification fields, timestamps
      - `minimalTicketData(array $overrides = []): array` — returns only the required fields for ticket creation
    - Each method merges `$overrides` via `array_merge()` so tests can customize specific fields
    - _Requirements: 2, 3, 15_

- [x] 2. Checkpoint — Verify shared infrastructure
  - Ensure all tests pass (`make phpunit`), ask the user if questions arise.

- [x] 3. Implement unit tests for entities and enums
  - [x] 3.1 Extend existing `TicketTest` with round-trip property tests
    - File: `src/tests/Unit/Entities/TicketTest.php`
    - Use `TicketDataBuilder` trait
    - Add test: `test_constructor_accepts_all_required_parameters` — construct a Ticket directly and assert all properties have correct types (Req 2.1)
    - Add test: `test_json_serialize_converts_enums_to_backing_values` — verify `jsonSerialize()` returns string backing values for category, priority, status, and nested metadata source/device_type (Req 2.2)
    - Add test: `test_from_row_maps_all_fields_correctly` — extend existing test to cover classification fields and resolved_at when populated (Req 2.3)
    - Add test: `test_to_row_returns_database_ready_array` — verify tags are JSON-encoded, metadata is flattened to prefixed columns, enums are backing values (Req 2.4)
    - Add test: `test_to_row_then_from_row_round_trip` — call `toRow()` then `fromRow()` on the result and assert all field values are equivalent (Req 2.5)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [x] 3.2 Create `TicketMetadataTest`
    - File: `src/tests/Unit/Entities/TicketMetadataTest.php`
    - Extends `PHPUnit\Framework\TestCase`, uses `TicketDataBuilder`
    - Add test: `test_constructs_with_all_null_parameters` — verify `new TicketMetadata()` with no args has null source, browser, deviceType (Req 15.1)
    - Add test: `test_from_row_maps_metadata_columns` — pass row with metadata_source, metadata_browser, metadata_device_type and verify enum/string mapping (Req 15.2)
    - Add test: `test_to_row_returns_prefixed_columns` — verify keys are metadata_source, metadata_browser, metadata_device_type with enum backing values or null (Req 15.3)
    - Add test: `test_to_row_then_from_row_round_trip` — verify round-trip equivalence for TicketMetadata (Req 15.4)
    - _Requirements: 15.1, 15.2, 15.3, 15.4_

  - [x] 3.3 Create `TicketEnumsTest`
    - File: `src/tests/Unit/Enums/TicketEnumsTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Add test: `test_category_enum_has_six_cases` — assert `Category::cases()` count is 6 and backing values match: account_access, technical_issue, billing_question, feature_request, bug_report, other (Req 16.1)
    - Add test: `test_priority_enum_has_four_cases` — assert 4 cases: urgent, high, medium, low (Req 16.2)
    - Add test: `test_status_enum_has_five_cases` — assert 5 cases: new, in_progress, waiting_customer, resolved, closed (Req 16.3)
    - Add test: `test_source_enum_has_five_cases` — assert 5 cases: web_form, email, api, chat, phone (Req 16.4)
    - Add test: `test_device_type_enum_has_four_cases` — assert 4 cases: desktop, mobile, tablet, other (Req 16.5)
    - Add test: `test_from_with_invalid_value_throws_value_error` — use a data provider across all 5 enums, call `::from('invalid')`, expect `ValueError` (Req 16.6)
    - Add test: `test_try_from_with_invalid_value_returns_null` — use a data provider across all 5 enums, call `::tryFrom('invalid')`, assert null (Req 16.7)
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7_

- [x] 4. Implement unit tests for filters, error rendering, and validation
  - [x] 4.1 Create `TicketFilterTest`
    - File: `src/tests/Unit/Filters/TicketFilterTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Add test: `test_from_params_with_empty_array_uses_defaults` — verify limit=50, offset=0, all filters null (Req 11.1)
    - Add test: `test_from_params_maps_all_supported_parameters` — pass all params (category, priority, status, customer_id, assigned_to, q, limit, offset, sort) and verify mapping (Req 11.2)
    - Add test: `test_limit_clamped_to_200_maximum` — pass limit=500, assert limit is 200 (Req 11.3)
    - Add test: `test_limit_clamped_to_1_minimum` — pass limit=0 and limit=-5, assert limit is 1 (Req 11.4)
    - Add test: `test_negative_offset_clamped_to_zero` — pass offset=-10, assert offset is 0 (Req 11.5)
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

  - [x] 4.2 Create `ErrorRendererTest`
    - File: `src/tests/Unit/Http/ErrorRendererTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Instantiate `ErrorRenderer` directly, invoke with exception stubs
    - Add test: `test_renders_validation_exception_as_json` — pass a `ValidationException` with field errors, assert JSON output has `error` = "Validation failed" and `details` with field keys (Req 13.1)
    - Add test: `test_renders_http_not_found_exception_as_json` — pass an `HttpNotFoundException`, assert JSON output has `error` = "Not found" (Req 13.2)
    - Add test: `test_renders_generic_throwable_as_internal_server_error` — pass a `\RuntimeException`, assert JSON output has `error` = "Internal server error" (Req 13.3)
    - _Requirements: 13.1, 13.2, 13.3_

  - [x] 4.3 Create `TicketValidatorTest`
    - File: `src/tests/Unit/Validation/TicketValidatorTest.php`
    - Extends `PHPUnit\Framework\TestCase`, uses `TicketDataBuilder`
    - Instantiate `TicketValidator` with a real `Somnambulist\Components\Validation\Factory`
    - Add test: `test_validate_create_passes_with_valid_data` — pass `validTicketData()`, no exception thrown (Req 3.1)
    - Add test: `test_validate_create_fails_on_missing_required_fields` — use a data provider for each required field (customer_id, customer_email, customer_name, subject, description, category, priority), remove the field, expect `ValidationException` listing that field (Req 3.2)
    - Add test: `test_validate_create_fails_on_invalid_email` — pass invalid email format, expect `ValidationException` with customer_email error (Req 3.3)
    - Add test: `test_validate_create_fails_on_subject_too_long` — pass subject with 201 chars, expect `ValidationException` (Req 3.4)
    - Add test: `test_validate_create_fails_on_description_too_short` — pass description with 5 chars, expect `ValidationException` (Req 3.5)
    - Add test: `test_validate_create_fails_on_invalid_category` — pass `category` = "nonexistent", expect `ValidationException` (Req 3.6)
    - Add test: `test_validate_create_fails_on_invalid_priority` — pass `priority` = "nonexistent", expect `ValidationException` (Req 3.7)
    - Add test: `test_validate_update_passes_with_valid_partial_data` — pass only `subject` field, no exception (Req 3.8)
    - Add test: `test_validate_update_fails_on_invalid_optional_field` — pass invalid email in update data, expect `ValidationException` (Req 3.9)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9_

- [x] 5. Checkpoint — Verify entity, enum, filter, error, and validation unit tests
  - Ensure all tests pass (`make phpunit`), ask the user if questions arise.

- [x] 6. Implement unit tests for parsers
  - [x] 6.1 Create `CsvTicketParserTest`
    - File: `src/tests/Unit/Parsers/CsvTicketParserTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Instantiate `CsvTicketParser` directly
    - Add test: `test_parse_valid_csv_yields_associative_arrays` — use `tests/fixtures/valid/sample_tickets.csv`, iterate results, assert each is an associative array with header-matching keys (Req 4.1)
    - Add test: `test_parse_normalizes_tags_to_array` — parse CSV with comma-separated tags column, assert `tags` field is a PHP array (Req 4.2)
    - Add test: `test_parse_normalizes_metadata_columns_to_nested_array` — parse CSV with metadata_source, metadata_browser, metadata_device_type columns, assert `metadata` key is a nested associative array (Req 4.3)
    - Add test: `test_parse_empty_string_throws_parse_exception` — pass empty string, expect `ParseException` (Req 4.4)
    - Add test: `test_parse_malformed_csv_throws_parse_exception` — use `tests/fixtures/invalid/malformed.csv` (Req 4.5)
    - Add test: `test_parse_escapes_formula_injection` — use `tests/fixtures/invalid/csv_injection.csv`, verify cells starting with `=`, `+`, `-`, `@` are escaped (Req 4.6)
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 6.2 Create `JsonTicketParserTest`
    - File: `src/tests/Unit/Parsers/JsonTicketParserTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Instantiate `JsonTicketParser` directly
    - Add test: `test_parse_valid_json_array_yields_associative_arrays` — parse a JSON array of ticket objects, assert each yielded item is an associative array (Req 5.1)
    - Add test: `test_parse_json_with_tickets_key_yields_nested_array` — parse `{"tickets": [...]}` format, assert tickets are yielded (Req 5.2)
    - Add test: `test_parse_empty_string_throws_parse_exception` — pass empty string, expect `ParseException` (Req 5.3)
    - Add test: `test_parse_malformed_json_throws_parse_exception` — use `tests/fixtures/invalid/malformed.json`, expect `ParseException` with JSON error description (Req 5.4)
    - Add test: `test_parse_non_array_json_throws_parse_exception` — pass `'"just a string"'`, expect `ParseException` (Req 5.5)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 6.3 Create `XmlTicketParserTest`
    - File: `src/tests/Unit/Parsers/XmlTicketParserTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Instantiate `XmlTicketParser` directly
    - Add test: `test_parse_valid_xml_yields_associative_arrays` — use `tests/fixtures/valid/sample_tickets.xml`, assert each yielded item is an associative array (Req 7.1)
    - Add test: `test_parse_xml_with_nested_metadata` — parse XML with `<metadata><source>...</source></metadata>`, assert nested associative array (Req 7.2)
    - Add test: `test_parse_xml_with_tags_elements` — parse XML with `<tags><tag>foo</tag></tags>`, assert tags is an array of strings (Req 7.3)
    - Add test: `test_parse_empty_string_throws_parse_exception` — pass empty string, expect `ParseException` (Req 7.4)
    - Add test: `test_parse_malformed_xml_throws_parse_exception` — use `tests/fixtures/invalid/malformed.xml` (Req 7.5)
    - Add test: `test_parse_xxe_xml_throws_parse_exception` — use `tests/fixtures/invalid/xxe.xml`, expect `ParseException` about DTD/entity declarations (Req 7.6)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [x] 6.4 Create `ParserRegistryTest`
    - File: `src/tests/Unit/Parsers/ParserRegistryTest.php`
    - Extends `PHPUnit\Framework\TestCase`
    - Construct `ParserRegistry` with mock/stub `TicketImportParserInterface` implementations keyed by MIME type
    - Add test: `test_resolve_csv_content_type` — resolve `text/csv`, assert returns the CSV parser (Req 8.1)
    - Add test: `test_resolve_json_content_type` — resolve `application/json`, assert returns the JSON parser (Req 8.2)
    - Add test: `test_resolve_xml_content_types` — resolve both `application/xml` and `text/xml`, assert returns the XML parser (Req 8.3)
    - Add test: `test_resolve_strips_charset_parameter` — resolve `application/json; charset=utf-8`, assert returns JSON parser (Req 8.4)
    - Add test: `test_resolve_unsupported_type_throws_parse_exception` — resolve `text/plain` with no format, expect `ParseException` (Req 8.5)
    - Add test: `test_resolve_falls_back_to_format_parameter` — resolve unsupported Content-Type with `format=json`, assert returns JSON parser (Req 8.6)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [x] 7. Implement unit tests for services
  - [x] 7.1 Create `ImportServiceTest`
    - File: `src/tests/Unit/Services/ImportServiceTest.php`
    - Extends `PHPUnit\Framework\TestCase`, uses `TicketDataBuilder`
    - Mock `ParserRegistry` and `TicketService` as constructor dependencies
    - Add test: `test_import_valid_content_returns_correct_summary` — mock parser to yield 3 valid rows, mock TicketService::create to succeed, assert ImportSummary has total=3, successful=3, failed=0 (Req 9.1)
    - Add test: `test_import_with_validation_failures_continues_processing` — mock parser to yield 3 rows, mock TicketService::create to throw `ValidationException` on row 2, assert ImportSummary has total=3, successful=2, failed=1, errors array contains row number and field (Req 9.2)
    - Add test: `test_import_parse_failure_returns_error_summary` — mock parser to throw `ParseException`, assert ImportSummary has total=0, successful=0, failed=1, errors contains parse message (Req 9.3)
    - Add test: `test_import_unsupported_content_type_propagates_exception` — mock ParserRegistry::resolve to throw `ParseException`, assert exception propagates (Req 9.4)
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [x] 7.2 Create `ClassificationTest`
    - File: `src/tests/Unit/Services/ClassificationTest.php`
    - Extends `AppTestCase` (needs DI container), uses `TicketDataBuilder`
    - Add test: `test_classifier_resolved_from_container_implements_interface` — resolve `ClassifierInterface::class` from container, assert `instanceof ClassifierInterface` (Req 6.1)
    - Add test: `test_classify_returns_valid_classification_result` — resolve classifier from container, call `classify()` with a Ticket, assert returns `ClassificationResult` (Req 6.2)
    - Add test: `test_classification_result_confidence_is_between_zero_and_one` — assert `$result->confidence >= 0.0 && $result->confidence <= 1.0` (Req 6.3)
    - Add test: `test_classification_result_reasoning_is_string` — assert `is_string($result->reasoning)` (Req 6.4)
    - Add test: `test_classification_result_keywords_is_array` — assert `is_array($result->keywords)` (Req 6.5)
    - Add test: `test_classification_result_is_readonly_with_public_properties` — verify `ClassificationResult` properties match constructor args via reflection or direct access (Req 6.6)
    - Add test: `test_ticket_service_create_with_auto_classify_persists_classification` — replace container's `ClassifierInterface` binding with a mock returning known confidence/reasoning/keywords, call `TicketService::create()` with `autoClassify: true`, read ticket back and verify classification fields are persisted (Req 6.7)
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [x] 8. Checkpoint — Verify all unit tests pass
  - Ensure all tests pass (`make phpunit`), ask the user if questions arise.

- [x] 9. Implement feature tests for ticket CRUD
  - [x] 9.1 Create `CreateTicketTest`
    - File: `src/tests/Feature/Tickets/CreateTicketTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_create_ticket_with_valid_data_returns_201` — POST /tickets with `validTicketData()`, assert 201 status, response JSON has `id` field (UUID), and all submitted fields are present (Req 1.1)
    - Add test: `test_create_ticket_with_invalid_data_returns_400` — POST /tickets with missing required fields, assert 400 status, response JSON has `error` = "Validation failed" and `details` object with field names (Req 1.2)
    - Add test: `test_create_ticket_with_invalid_email_returns_400` — POST /tickets with bad email, assert 400 with `customer_email` in details (Req 1.2)
    - Add test: `test_create_ticket_with_auto_classify_returns_classification` — POST /tickets?auto_classify=true, assert 201 with non-null classification fields (Req 1.11)
    - _Requirements: 1.1, 1.2, 1.11_

  - [x] 9.2 Create `ReadTicketTest`
    - File: `src/tests/Feature/Tickets/ReadTicketTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_list_tickets_returns_200_with_array` — create a ticket, GET /tickets, assert 200 with JSON array (Req 1.3)
    - Add test: `test_list_tickets_with_filter_returns_matching_only` — create tickets with different categories, GET /tickets?category=..., assert only matching tickets returned (Req 1.4)
    - Add test: `test_show_existing_ticket_returns_200` — create a ticket, GET /tickets/{id}, assert 200 with ticket JSON (Req 1.5)
    - Add test: `test_show_nonexistent_ticket_returns_404` — GET /tickets/nonexistent-id, assert 404 with `{"error": "Not found"}` (Req 1.6)
    - _Requirements: 1.3, 1.4, 1.5, 1.6_

  - [x] 9.3 Create `UpdateTicketTest`
    - File: `src/tests/Feature/Tickets/UpdateTicketTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_update_ticket_with_valid_data_returns_200` — create a ticket, PUT /tickets/{id} with partial data (e.g., new subject), assert 200 with updated ticket JSON (Req 1.7)
    - Add test: `test_update_ticket_with_invalid_data_returns_400` — create a ticket, PUT /tickets/{id} with invalid email, assert 400 with validation error details (Req 1.8)
    - _Requirements: 1.7, 1.8_

  - [x] 9.4 Create `DeleteTicketTest`
    - File: `src/tests/Feature/Tickets/DeleteTicketTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_delete_existing_ticket_returns_204` — create a ticket, DELETE /tickets/{id}, assert 204 with no body (Req 1.9)
    - Add test: `test_delete_nonexistent_ticket_returns_404` — DELETE /tickets/nonexistent-id, assert 404 (Req 1.10)
    - _Requirements: 1.9, 1.10_

  - [x] 9.5 Create `AutoClassifyTest`
    - File: `src/tests/Feature/Tickets/AutoClassifyTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_auto_classify_existing_ticket_returns_200_with_classification` — create a ticket, POST /tickets/{id}/auto-classify, assert 200 with classification fields (confidence, reasoning, keywords) (Req 1.12)
    - Add test: `test_auto_classify_nonexistent_ticket_returns_404` — POST /tickets/nonexistent-id/auto-classify, assert 404 with `{"error": "Not found"}` (Req 1.13)
    - _Requirements: 1.12, 1.13_

- [x] 10. Implement feature tests for listing, filtering, and import
  - [x] 10.1 Create `ListFilterTest`
    - File: `src/tests/Feature/Tickets/ListFilterTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Seed multiple tickets with varying category, priority, status, subject/description content
    - Add test: `test_filter_by_category` — GET /tickets?category=technical_issue, assert only matching tickets (Req 12.1)
    - Add test: `test_filter_by_priority` — GET /tickets?priority=high, assert only matching tickets (Req 12.2)
    - Add test: `test_filter_by_status` — GET /tickets?status=new, assert only matching tickets (Req 12.3)
    - Add test: `test_search_by_q_parameter` — GET /tickets?q=searchterm, assert only tickets with matching subject or description (Req 12.4)
    - Add test: `test_pagination_with_limit_and_offset` — create 5 tickets, GET /tickets?limit=2&offset=0, assert 2 results; GET /tickets?limit=2&offset=2, assert next 2 results (Req 12.5)
    - Add test: `test_sort_by_created_at_descending` — GET /tickets?sort=-created_at, assert tickets ordered newest first (Req 12.6)
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

  - [x] 10.2 Create `ImportTicketTest`
    - File: `src/tests/Feature/Tickets/ImportTicketTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_import_valid_csv_returns_success_summary` — POST /tickets/import with `tests/fixtures/valid/sample_tickets.csv` content and Content-Type `text/csv`, assert 200 with ImportSummary showing all successful (Req 10.1)
    - Add test: `test_import_valid_json_returns_success_summary` — POST /tickets/import with `tests/fixtures/valid/sample_tickets.json` content and Content-Type `application/json`, assert 200 with all successful (Req 10.2)
    - Add test: `test_import_valid_xml_returns_success_summary` — POST /tickets/import with `tests/fixtures/valid/sample_tickets.xml` content and Content-Type `application/xml`, assert 200 with all successful (Req 10.3)
    - Add test: `test_import_with_invalid_rows_returns_partial_failure` — POST /tickets/import with content containing some invalid rows, assert 200 with failed > 0 and errors array (Req 10.4)
    - Add test: `test_import_unsupported_content_type_returns_error` — POST /tickets/import with Content-Type `text/plain`, assert error response (Req 10.5)
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 11. Checkpoint — Verify all feature tests pass
  - Ensure all tests pass (`make phpunit`), ask the user if questions arise.

- [x] 12. Implement integration tests
  - [x] 12.1 Create `TicketLifecycleTest`
    - File: `src/tests/Integration/TicketLifecycleTest.php`
    - Extends `AppTestCase`, uses `TicketDataBuilder`
    - Add test: `test_full_ticket_lifecycle` — create ticket (POST, assert 201), read ticket (GET /{id}, assert 200 with matching data), update ticket (PUT /{id}, assert 200 with changed fields), verify update (GET /{id}, assert updated values), delete ticket (DELETE /{id}, assert 204), confirm gone (GET /{id}, assert 404) (Req 14.1)
    - Add test: `test_import_then_list` — import tickets via POST /tickets/import with valid CSV, then GET /tickets and confirm imported tickets are present in the listing (Req 14.2)
    - Add test: `test_combined_category_and_priority_filter` — create tickets with different category/priority combinations, GET /tickets?category=X&priority=Y, assert only matching tickets returned (Req 14.3)
    - Add test: `test_create_with_auto_classify_then_read_classification` — POST /tickets?auto_classify=true, then GET /tickets/{id}, assert classification data (confidence, reasoning, keywords) is present and retrievable (Req 14.4)
    - _Requirements: 14.1, 14.2, 14.3, 14.4_

- [x] 13. Update `phpunit.xml` to include Integration test suite
  - Add an `Integration` test suite entry to `src/phpunit.xml` pointing to `tests/Integration` directory
  - _Requirements: 14_

- [x] 14. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass (`make phpunit`), ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- All tests run via `make phpunit` inside Docker — never run `php` or `phpunit` on the host
- Unit tests extend `PHPUnit\Framework\TestCase` directly (no app, no DB)
- Feature and integration tests extend `AppTestCase` (in-memory SQLite + full Slim app)
- The `TicketDataBuilder` trait is shared across all test tiers to keep fixture data consistent
