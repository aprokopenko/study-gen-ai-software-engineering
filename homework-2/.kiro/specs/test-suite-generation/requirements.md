# Requirements Document

## Introduction

This specification defines the comprehensive test suite for the Intelligent Customer Support System — a PHP REST API built with Slim 4, PHP-DI, Medoo, and SQLite. The test suite covers API endpoint feature tests, data model unit tests, multi-format import parsing, auto-classification, integration workflows, and edge cases. The target is >85% code coverage using PHPUnit 11.

## Glossary

- **API**: The Slim 4 REST application handling HTTP requests for ticket management
- **Ticket**: The core domain entity representing a customer support ticket with fields for customer info, subject, description, category, priority, status, tags, metadata, and classification data
- **TicketService**: The service layer orchestrating ticket CRUD operations, validation, and classification
- **TicketValidator**: The validation component enforcing field rules on create and update operations
- **TicketRepository**: The data-access layer persisting Ticket entities to SQLite via Medoo
- **TicketFilter**: A value object encapsulating query parameters for listing tickets (category, priority, status, customer_id, assigned_to, q, limit, offset, sort)
- **ImportService**: The service coordinating bulk ticket import from raw file content
- **ImportSummary**: A value object holding import results (total, successful, failed, errors)
- **ParserRegistry**: The component resolving a TicketImportParserInterface implementation by Content-Type or format query parameter
- **CsvTicketParser**: The parser converting raw CSV content into normalized ticket row arrays
- **JsonTicketParser**: The parser converting raw JSON content into normalized ticket row arrays
- **XmlTicketParser**: The parser converting raw XML content into normalized ticket row arrays
- **ClassifierInterface**: The interface contract for ticket classification, resolved from the DI container
- **NullClassifier**: The default ClassifierInterface implementation returning zero-confidence results
- **ClassificationResult**: A readonly value object holding classification output (confidence, reasoning, keywords)
- **AppTestCase**: The base PHPUnit test case providing an in-memory SQLite app instance with helper methods (get, postJson, postRaw)
- **ErrorRenderer**: The Slim error renderer producing JSON error responses for ValidationException and HttpNotFoundException
- **Category_Enum**: The backed string enum with values: account_access, technical_issue, billing_question, feature_request, bug_report, other
- **Priority_Enum**: The backed string enum with values: urgent, high, medium, low
- **Status_Enum**: The backed string enum with values: new, in_progress, waiting_customer, resolved, closed
- **Source_Enum**: The backed string enum with values: web_form, email, api, chat, phone
- **DeviceType_Enum**: The backed string enum with values: desktop, mobile, tablet, other

## Requirements

### Requirement 1: API Endpoint Feature Tests

**User Story:** As a developer, I want feature tests for every API endpoint, so that I can verify the full HTTP request-response cycle works correctly including routing, validation, serialization, and status codes.

#### Acceptance Criteria

1. WHEN a POST request with valid ticket data is sent to `/tickets`, THE API SHALL return a 201 status with the created ticket JSON including a generated UUID `id` field
2. WHEN a POST request with invalid data (missing required fields or invalid email) is sent to `/tickets`, THE API SHALL return a 400 status with a JSON body containing an `error` key set to "Validation failed" and a `details` object whose keys include the specific field names that failed validation (e.g., `customer_email` for an invalid email)
3. WHEN a GET request is sent to `/tickets`, THE API SHALL return a 200 status with a JSON array of ticket objects
4. WHEN a GET request is sent to `/tickets` with filter query parameters (category, priority, status, customer_id, assigned_to, q), THE API SHALL return only tickets matching all specified filters
5. WHEN a GET request is sent to `/tickets/{id}` with a valid existing ticket ID, THE API SHALL return a 200 status with the ticket JSON
6. WHEN a GET request is sent to `/tickets/{id}` with a non-existent ID, THE API SHALL return a 404 status with a JSON body containing `{"error": "Not found"}`
7. WHEN a PUT request with valid partial data is sent to `/tickets/{id}` for an existing ticket, THE API SHALL return a 200 status with the fully updated ticket JSON
8. WHEN a PUT request with invalid data (e.g., invalid email format or out-of-range string length) is sent to `/tickets/{id}`, THE API SHALL return a 400 status with a JSON body containing an `error` key set to "Validation failed" and a `details` object whose keys include the specific field names that failed validation
9. WHEN a DELETE request is sent to `/tickets/{id}` for an existing ticket, THE API SHALL return a 204 status with no body
10. WHEN a DELETE request is sent to `/tickets/{id}` for a non-existent ticket, THE API SHALL return a 404 status
11. WHEN a POST request with valid ticket data and query parameter `auto_classify=true` is sent to `/tickets`, THE API SHALL return a 201 status with the created ticket JSON including non-null `classification` fields (confidence, reasoning, keywords)
12. WHEN a POST request is sent to `/tickets/{id}/auto-classify` for an existing ticket, THE API SHALL return a 200 status with the ticket JSON including classification fields (category, priority, confidence, reasoning, keywords)
13. WHEN a POST request is sent to `/tickets/{id}/auto-classify` for a non-existent ticket, THE API SHALL return a 404 status with a JSON body containing `{"error": "Not found"}`

### Requirement 2: Ticket Entity Unit Tests

**User Story:** As a developer, I want unit tests for the Ticket entity, so that I can verify serialization, deserialization, and data integrity of the domain model.

#### Acceptance Criteria

1. THE Ticket entity SHALL construct a valid instance from all required constructor parameters with correct types
2. WHEN `jsonSerialize()` is called on a Ticket, THE Ticket entity SHALL return an associative array containing all ticket fields with enum values serialized to their string backing values
3. WHEN `fromRow()` is called with a valid database row array, THE Ticket entity SHALL return a Ticket instance with all fields correctly mapped
4. WHEN `toRow()` is called on a Ticket, THE Ticket entity SHALL return an associative array suitable for database insertion with tags JSON-encoded and metadata flattened to prefixed columns
5. FOR ALL valid Ticket instances, calling `toRow()` then `fromRow()` on the result SHALL produce a Ticket with equivalent field values (round-trip property)

### Requirement 3: Ticket Validation Unit Tests

**User Story:** As a developer, I want unit tests for TicketValidator, so that I can verify all field validation rules are enforced correctly.

#### Acceptance Criteria

1. WHEN `validateCreate()` is called with valid complete ticket data, THE TicketValidator SHALL not throw any exception
2. WHEN `validateCreate()` is called with a missing required field (customer_id, customer_email, customer_name, subject, description, category, or priority), THE TicketValidator SHALL throw a ValidationException listing the missing field
3. WHEN `validateCreate()` is called with an invalid email format for `customer_email`, THE TicketValidator SHALL throw a ValidationException with an error for the `customer_email` field
4. WHEN `validateCreate()` is called with a `subject` shorter than 1 character or longer than 200 characters, THE TicketValidator SHALL throw a ValidationException with an error for the `subject` field
5. WHEN `validateCreate()` is called with a `description` shorter than 10 characters or longer than 2000 characters, THE TicketValidator SHALL throw a ValidationException with an error for the `description` field
6. WHEN `validateCreate()` is called with an invalid `category` value not in the Category_Enum, THE TicketValidator SHALL throw a ValidationException with an error for the `category` field
7. WHEN `validateCreate()` is called with an invalid `priority` value not in the Priority_Enum, THE TicketValidator SHALL throw a ValidationException with an error for the `priority` field
8. WHEN `validateUpdate()` is called with valid partial data, THE TicketValidator SHALL not throw any exception
9. WHEN `validateUpdate()` is called with an invalid optional field value, THE TicketValidator SHALL throw a ValidationException listing the invalid field

### Requirement 4: CSV Parser Unit Tests

**User Story:** As a developer, I want unit tests for CsvTicketParser, so that I can verify correct parsing, normalization, and error handling of CSV ticket imports.

#### Acceptance Criteria

1. WHEN `parse()` is called with valid CSV content containing a header row and data rows, THE CsvTicketParser SHALL yield associative arrays with keys matching the header columns
2. WHEN `parse()` is called with CSV content containing comma-separated tags, THE CsvTicketParser SHALL normalize the `tags` field into a PHP array
3. WHEN `parse()` is called with CSV content containing `metadata_source`, `metadata_browser`, and `metadata_device_type` columns, THE CsvTicketParser SHALL normalize the metadata columns into a nested `metadata` associative array
4. WHEN `parse()` is called with an empty string, THE CsvTicketParser SHALL throw a ParseException with a message indicating the input is empty
5. WHEN `parse()` is called with malformed CSV content, THE CsvTicketParser SHALL throw a ParseException
6. WHEN `parse()` is called with CSV content containing formula injection patterns (e.g., cells starting with `=`, `+`, `-`, `@`), THE CsvTicketParser SHALL escape the formula characters using the EscapeFormula formatter

### Requirement 5: JSON Parser Unit Tests

**User Story:** As a developer, I want unit tests for JsonTicketParser, so that I can verify correct parsing and error handling of JSON ticket imports.

#### Acceptance Criteria

1. WHEN `parse()` is called with a valid JSON array of ticket objects, THE JsonTicketParser SHALL yield each ticket object as an associative array
2. WHEN `parse()` is called with a valid JSON object containing a `tickets` key with an array value, THE JsonTicketParser SHALL yield each ticket from the nested array
3. WHEN `parse()` is called with an empty string, THE JsonTicketParser SHALL throw a ParseException with a message indicating the input is empty
4. WHEN `parse()` is called with malformed JSON content, THE JsonTicketParser SHALL throw a ParseException with a message containing the JSON error description
5. WHEN `parse()` is called with a JSON value that is not an array or object, THE JsonTicketParser SHALL throw a ParseException

### Requirement 6: Classification Unit Tests

**User Story:** As a developer, I want unit tests for the classification subsystem resolved through ClassifierInterface, so that I can verify the interface contract, ClassificationResult value object, and TicketService auto-classify integration.

#### Acceptance Criteria

1. WHEN the ClassifierInterface is resolved from the DI container, THE resolved instance SHALL implement ClassifierInterface
2. WHEN `classify()` is called on the container-resolved ClassifierInterface with a valid Ticket, THE ClassifierInterface implementation SHALL return a valid ClassificationResult
3. WHEN `classify()` is called on the container-resolved ClassifierInterface, THE returned ClassificationResult SHALL have a `confidence` property that is a float between 0.0 and 1.0 inclusive
4. WHEN `classify()` is called on the container-resolved ClassifierInterface, THE returned ClassificationResult SHALL have a `reasoning` property that is a string
5. WHEN `classify()` is called on the container-resolved ClassifierInterface, THE returned ClassificationResult SHALL have a `keywords` property that is an array
6. THE ClassificationResult value object SHALL be readonly and expose public `confidence`, `reasoning`, and `keywords` properties matching the constructor arguments
7. WHEN `TicketService::create()` is called with `autoClassify` set to true, THE TicketService SHALL invoke `ClassifierInterface::classify()` and persist the classification fields (confidence, reasoning, keywords) on the created Ticket

### Requirement 7: XML Parser Unit Tests

**User Story:** As a developer, I want unit tests for XmlTicketParser, so that I can verify correct parsing, nested element handling, and security protections.

#### Acceptance Criteria

1. WHEN `parse()` is called with valid XML containing `<ticket>` elements, THE XmlTicketParser SHALL yield each ticket as an associative array with element names as keys
2. WHEN `parse()` is called with XML containing nested `<metadata>` elements, THE XmlTicketParser SHALL produce a nested associative array for the metadata field
3. WHEN `parse()` is called with XML containing `<tags><tag>` child elements, THE XmlTicketParser SHALL produce an array of tag strings for the `tags` field
4. WHEN `parse()` is called with an empty string, THE XmlTicketParser SHALL throw a ParseException with a message indicating the input is empty
5. WHEN `parse()` is called with malformed XML content, THE XmlTicketParser SHALL throw a ParseException
6. WHEN `parse()` is called with XML containing a `<!DOCTYPE>` or `<!ENTITY>` declaration, THE XmlTicketParser SHALL throw a ParseException indicating DTD/entity declarations are not allowed

### Requirement 8: Parser Registry Unit Tests

**User Story:** As a developer, I want unit tests for ParserRegistry, so that I can verify correct parser resolution by Content-Type and format parameter.

#### Acceptance Criteria

1. WHEN `resolve()` is called with Content-Type `text/csv`, THE ParserRegistry SHALL return the CsvTicketParser instance
2. WHEN `resolve()` is called with Content-Type `application/json`, THE ParserRegistry SHALL return the JsonTicketParser instance
3. WHEN `resolve()` is called with Content-Type `application/xml` or `text/xml`, THE ParserRegistry SHALL return the XmlTicketParser instance
4. WHEN `resolve()` is called with a Content-Type containing parameters (e.g., `application/json; charset=utf-8`), THE ParserRegistry SHALL strip the parameters and resolve by the base MIME type
5. WHEN `resolve()` is called with an unsupported Content-Type and no format parameter, THE ParserRegistry SHALL throw a ParseException
6. WHEN `resolve()` is called with an unsupported Content-Type but a valid `format` parameter (csv, json, or xml), THE ParserRegistry SHALL resolve the parser using the format parameter as fallback

### Requirement 9: Import Service Unit Tests

**User Story:** As a developer, I want unit tests for ImportService, so that I can verify bulk import orchestration, per-row error handling, and summary generation.

#### Acceptance Criteria

1. WHEN `import()` is called with valid content and Content-Type, THE ImportService SHALL return an ImportSummary with `total` equal to the number of rows, `successful` equal to the number of rows that passed validation, and `failed` equal to zero
2. WHEN `import()` is called with content containing rows that fail validation, THE ImportService SHALL continue processing remaining rows and return an ImportSummary with `failed` count and `errors` array containing the row number, field name, and error message for each failure
3. WHEN `import()` is called with content that the parser cannot parse (ParseException), THE ImportService SHALL return an ImportSummary with `total` of 0, `successful` of 0, `failed` of 1, and the parse error message in the errors array
4. WHEN `import()` is called with an unsupported Content-Type, THE ImportService SHALL propagate the ParseException from ParserRegistry

### Requirement 10: Import Feature Tests

**User Story:** As a developer, I want feature tests for the `/tickets/import` endpoint, so that I can verify the full HTTP import workflow for each supported format.

#### Acceptance Criteria

1. WHEN a POST request with valid CSV content and Content-Type `text/csv` is sent to `/tickets/import`, THE API SHALL return a 200 status with an ImportSummary JSON showing all tickets successfully imported
2. WHEN a POST request with valid JSON content and Content-Type `application/json` is sent to `/tickets/import`, THE API SHALL return a 200 status with an ImportSummary JSON showing all tickets successfully imported
3. WHEN a POST request with valid XML content and Content-Type `application/xml` is sent to `/tickets/import`, THE API SHALL return a 200 status with an ImportSummary JSON showing all tickets successfully imported
4. WHEN a POST request with content containing some invalid rows is sent to `/tickets/import`, THE API SHALL return a 200 status with an ImportSummary JSON where `failed` is greater than zero and `errors` contains details for each failed row
5. WHEN a POST request with an unsupported Content-Type is sent to `/tickets/import`, THE API SHALL return an error response

### Requirement 11: Ticket Filter Unit Tests

**User Story:** As a developer, I want unit tests for TicketFilter, so that I can verify query parameter parsing, default values, and boundary clamping.

#### Acceptance Criteria

1. WHEN `fromParams()` is called with an empty array, THE TicketFilter SHALL use default values: limit of 50, offset of 0, and all filter fields set to null
2. WHEN `fromParams()` is called with all supported filter parameters, THE TicketFilter SHALL map each parameter to the corresponding property
3. WHEN `fromParams()` is called with a `limit` value greater than 200, THE TicketFilter SHALL clamp the limit to 200
4. WHEN `fromParams()` is called with a `limit` value less than 1, THE TicketFilter SHALL clamp the limit to 1
5. WHEN `fromParams()` is called with a negative `offset` value, THE TicketFilter SHALL clamp the offset to 0

### Requirement 12: Listing and Filtering Feature Tests

**User Story:** As a developer, I want feature tests for the GET `/tickets` endpoint with filtering, so that I can verify that query parameters correctly filter, paginate, and sort results.

#### Acceptance Criteria

1. WHEN a GET request is sent to `/tickets` with a `category` query parameter, THE API SHALL return only tickets matching that category
2. WHEN a GET request is sent to `/tickets` with a `priority` query parameter, THE API SHALL return only tickets matching that priority
3. WHEN a GET request is sent to `/tickets` with a `status` query parameter, THE API SHALL return only tickets matching that status
4. WHEN a GET request is sent to `/tickets` with a `q` query parameter, THE API SHALL return only tickets whose subject or description contains the search term
5. WHEN a GET request is sent to `/tickets` with `limit` and `offset` query parameters, THE API SHALL return at most `limit` tickets starting from the given offset
6. WHEN a GET request is sent to `/tickets` with a `sort` query parameter (e.g., `-created_at`), THE API SHALL return tickets ordered by the specified field and direction

### Requirement 13: Error Rendering Unit Tests

**User Story:** As a developer, I want unit tests for ErrorRenderer, so that I can verify that exceptions are rendered as correct JSON error responses.

#### Acceptance Criteria

1. WHEN ErrorRenderer is invoked with a ValidationException, THE ErrorRenderer SHALL return a JSON string with `error` set to "Validation failed" and `details` containing the field-level error messages
2. WHEN ErrorRenderer is invoked with an HttpNotFoundException, THE ErrorRenderer SHALL return a JSON string with `error` set to "Not found"
3. WHEN ErrorRenderer is invoked with a generic Throwable, THE ErrorRenderer SHALL return a JSON string with `error` set to "Internal server error"

### Requirement 14: Integration Workflow Tests

**User Story:** As a developer, I want integration tests covering end-to-end workflows, so that I can verify that multiple operations compose correctly across the full stack.

#### Acceptance Criteria

1. THE Integration_Test_Suite SHALL verify a complete ticket lifecycle: create a ticket, read the ticket, update the ticket, verify the update, delete the ticket, and confirm the ticket returns 404
2. THE Integration_Test_Suite SHALL verify a bulk import followed by listing: import tickets via `/tickets/import`, then GET `/tickets` and confirm the imported tickets are present
3. WHEN multiple tickets with different categories and priorities exist, THE Integration_Test_Suite SHALL verify that combined filtering by category and priority returns only matching tickets
4. THE Integration_Test_Suite SHALL verify that creating a ticket with `auto_classify=true` stores classification data that is retrievable via GET `/tickets/{id}`

### Requirement 15: TicketMetadata Unit Tests

**User Story:** As a developer, I want unit tests for the TicketMetadata value object, so that I can verify construction, serialization, and deserialization of metadata fields.

#### Acceptance Criteria

1. THE TicketMetadata entity SHALL construct a valid instance with all-null optional parameters (source, browser, deviceType)
2. WHEN `fromRow()` is called with a database row containing `metadata_source`, `metadata_browser`, and `metadata_device_type` columns, THE TicketMetadata entity SHALL return an instance with the corresponding enum and string values
3. WHEN `toRow()` is called on a TicketMetadata instance, THE TicketMetadata entity SHALL return an associative array with keys `metadata_source`, `metadata_browser`, and `metadata_device_type` containing the enum backing values or null
4. FOR ALL valid TicketMetadata instances, calling `toRow()` then `fromRow()` on the result SHALL produce a TicketMetadata with equivalent field values (round-trip property)

### Requirement 16: Enum Unit Tests

**User Story:** As a developer, I want unit tests for all backed enums, so that I can verify that enum cases, backing values, and from/tryFrom behavior are correct.

#### Acceptance Criteria

1. THE Category_Enum SHALL have exactly 6 cases with backing values: account_access, technical_issue, billing_question, feature_request, bug_report, other
2. THE Priority_Enum SHALL have exactly 4 cases with backing values: urgent, high, medium, low
3. THE Status_Enum SHALL have exactly 5 cases with backing values: new, in_progress, waiting_customer, resolved, closed
4. THE Source_Enum SHALL have exactly 5 cases with backing values: web_form, email, api, chat, phone
5. THE DeviceType_Enum SHALL have exactly 4 cases with backing values: desktop, mobile, tablet, other
6. WHEN `from()` is called on any enum with an invalid backing value, THE enum SHALL throw a ValueError
7. WHEN `tryFrom()` is called on any enum with an invalid backing value, THE enum SHALL return null
