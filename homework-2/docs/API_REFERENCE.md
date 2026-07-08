# 🎫 Intelligent Customer Support System — API Reference

Complete documentation of all REST API endpoints, data models, and response formats for the Support Ticket Management System.

**Base URL**: `http://localhost:3000`  
**API Version**: 1.0  
**Content-Type**: `application/json` (unless specified otherwise)

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Data Models](#data-models)
4. [Error Handling](#error-handling)
5. [Endpoints](#endpoints)
   - [Health Check](#health-check)
   - [Tickets](#tickets)
   - [Bulk Import](#bulk-import)
   - [Auto-Classification](#auto-classification)

---

## Overview

The Support Ticket API provides full CRUD operations for managing customer support tickets with advanced features like multi-format bulk import and automatic categorization.

### Key Features

- **RESTful Design** — Standard HTTP methods and status codes
- **JSON Responses** — All responses are JSON (except health check)
- **Batch Operations** — Import CSV, JSON, or XML files
- **Smart Classification** — Automatic category and priority assignment
- **Comprehensive Validation** — Detailed error messages for invalid requests
- **UUID Identifiers** — All tickets use UUID v4 identifiers

---

## Authentication

Currently, the API requires **no authentication**. In production, implement API key or OAuth 2.0 authentication.

> **Future Enhancement**: Add Bearer token authentication

---

## Data Models

### Ticket Schema

The core data model for support tickets.

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customer_id": "string (required, 1-255 chars)",
  "customer_email": "string (required, valid email)",
  "customer_name": "string (required, 1-200 chars)",
  "subject": "string (required, 1-200 chars)",
  "description": "string (required, 10-2000 chars)",
  "category": "account_access | technical_issue | billing_question | feature_request | bug_report | other",
  "priority": "urgent | high | medium | low",
  "status": "new | in_progress | waiting_customer | resolved | closed",
  "assigned_to": "string (optional, 1-255 chars)",
  "tags": ["string", "array"],
  "metadata": {
    "source": "web_form | email | api | chat | phone",
    "browser": "string (optional)",
    "device_type": "desktop | mobile | tablet"
  },
  "classification": {
    "confidence": 0.95,
    "reasoning": "Keywords detected: ...",
    "keywords": ["array", "of", "matched", "keywords"]
  },
  "created_at": "2026-05-02T14:26:03+00:00",
  "updated_at": "2026-05-02T14:26:03+00:00",
  "resolved_at": null
}
```

### Field Descriptions

| Field | Type | Required | Constraints | Example |
|-------|------|----------|-------------|---------|
| `id` | UUID | Auto | Generated on creation | `550e8400-e29b-41d4-a716-446655440000` |
| `customer_id` | String | Yes | Required, no max length | `CUST123` |
| `customer_email` | String | Yes | Valid email format (RFC 5322) | `john@example.com` |
| `customer_name` | String | Yes | Required, no max length | `John Doe` |
| `subject` | String | Yes | 1-200 characters | `Login not working` |
| `description` | String | Yes | 10-2000 characters | `I cannot log in...` |
| `category` | Enum | Yes | One of 6 values | `account_access` |
| `priority` | Enum | Yes | urgent, high, medium, low | `high` |
| `status` | Enum | No | Defaults to "new" | `new` |
| `assigned_to` | String | No | 1-255 characters, nullable | `agent@company.com` |
| `tags` | Array | No | Array of strings | `["urgent", "security"]` |
| `metadata.source` | Enum | No | Five source types | `web_form` |
| `metadata.browser` | String | No | Browser name | `Chrome` |
| `metadata.device_type` | Enum | No | desktop, mobile, tablet, other | `desktop` |
| `classification.confidence` | Float | No | 0.0 to 1.0 | `0.95` |
| `classification.reasoning` | String | No | Human-readable explanation | `Keywords found: ...` |
| `classification.keywords` | Array | No | Matched keywords | `["critical", "urgent"]` |
| `created_at` | ISO 8601 | Auto | UTC timestamp | `2026-05-02T14:26:03+00:00` |
| `updated_at` | ISO 8601 | Auto | UTC timestamp | `2026-05-02T14:26:03+00:00` |
| `resolved_at` | ISO 8601 | No | UTC timestamp, nullable | `2026-05-02T16:30:00+00:00` |

### Category Definitions

| Category | Description | Keywords (for auto-classification) |
|----------|-------------|----------|
| `account_access` | Login, password, 2FA issues | login, password, 2fa, sign in, locked out |
| `technical_issue` | Bugs, errors, crashes | error, crash, freeze, broken |
| `billing_question` | Payments, invoices, refunds | invoice, payment, refund, charge, billing |
| `feature_request` | Enhancements, suggestions | feature, suggestion, would be nice, please add |
| `bug_report` | Defects with reproduction steps | bug, defect, reproduce, steps to reproduce, failure |
| `other` | Uncategorizable | fallback category (no specific keywords) |

### Priority Levels

| Priority | Urgency | Keywords (for auto-classification) |
|----------|---------|----------|
| `urgent` | Critical, immediate action | can't access, critical, production down, security |
| `high` | Important, should resolve soon | important, blocking, asap |
| `medium` | Normal priority | default (no specific keywords trigger this) |
| `low` | Minor issues, can wait | minor, cosmetic, suggestion |

---

## Error Handling

### HTTP Status Codes

| Status | Meaning | Use Case |
|--------|---------|----------|
| `200 OK` | Request succeeded | Successful GET, PUT |
| `201 Created` | Resource created | Successful POST to create ticket |
| `204 No Content` | Resource deleted | Successful DELETE |
| `400 Bad Request` | Validation error | Invalid data in request |
| `404 Not Found` | Resource not found | Ticket ID doesn't exist |
| `500 Internal Server Error` | Server error | Unexpected server failure |

### Error Response Format

#### Validation Error (400)

```json
{
  "error": "Validation failed",
  "details": {
    "customer_email": "Invalid email format",
    "description": "Must be at least 10 characters"
  }
}
```

#### Not Found (404)

```json
{
  "error": "Not found"
}
```

#### Server Error (500)

```json
{
  "error": "Internal server error"
}
```

### Common Validation Errors

| Field | Error Message | Fix |
|-------|---------------|-----|
| `customer_email` | Invalid email format | Use valid email (user@domain.com) |
| `customer_name` | Must be 1-200 characters | Provide name between 1-200 chars |
| `subject` | Must be 1-200 characters | Provide subject between 1-200 chars |
| `description` | Must be 10-2000 characters | Provide description 10-2000 chars |
| `category` | Invalid category | Use: account_access, technical_issue, billing_question, feature_request, bug_report, other |
| `priority` | Invalid priority | Use: urgent, high, medium, low |
| `status` | Invalid status | Use: new, in_progress, waiting_customer, resolved, closed |

---

## Endpoints

### Health Check

Check API availability.

#### `GET /`

**Description**: Health check endpoint. Use to verify API is running.

**Request**

```bash
curl http://localhost:3000/
```

**Response (200 OK)**

```json
{
  "status": "ok",
  "message": "Support Ticket API"
}
```

---

### Tickets

#### `POST /tickets` — Create Ticket

Create a new support ticket.

**Request**

```bash
curl -X POST http://localhost:3000/tickets \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "CUST001",
    "customer_email": "john@example.com",
    "customer_name": "John Doe",
    "subject": "Cannot log in to account",
    "description": "I am unable to log into my account. I have tried resetting my password but still cannot access the system.",
    "category": "account_access",
    "priority": "high",
    "status": "new",
    "tags": ["urgent", "security"],
    "metadata": {
      "source": "web_form",
      "browser": "Chrome",
      "device_type": "desktop"
    }
  }'
```

**Request Body** (required fields: `customer_id`, `customer_email`, `customer_name`, `subject`, `description`)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `customer_id` | String | Yes      | Unique customer identifier |
| `customer_email` | String | Yes      | Valid email address |
| `customer_name` | String | Yes      | Customer full name |
| `subject` | String | Yes      | Brief description, 1-200 characters |
| `description` | String | Yes      | Detailed description, 10-2000 characters |
| `category` | String | No       | One of: account_access, technical_issue, billing_question, feature_request, bug_report, other |
| `priority` | String | No       | One of: urgent, high, medium, low |
| `status` | String | No       | Defaults to "new"; one of: new, in_progress, waiting_customer, resolved, closed |
| `assigned_to` | String | No       | Support agent name (no length limit) |
| `tags` | Array | No       | Array of custom tag strings (max 50 chars each) |
| `metadata.source` | Enum | No       | One of: web_form, email, api, chat, phone |
| `metadata.browser` | String | No       | Browser name (e.g., "Chrome", "Safari") |
| `metadata.device_type` | Enum | No       | One of: desktop, mobile, tablet, other |

**Response (201 Created)**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customer_id": "CUST001",
  "customer_email": "john@example.com",
  "customer_name": "John Doe",
  "subject": "Cannot log in to account",
  "description": "I am unable to log into my account...",
  "category": "account_access",
  "priority": "high",
  "status": "new",
  "assigned_to": null,
  "tags": ["urgent", "security"],
  "metadata": {
    "source": "web_form",
    "browser": "Chrome",
    "device_type": "desktop"
  },
  "classification": {
    "confidence": null,
    "reasoning": null,
    "keywords": null
  },
  "created_at": "2026-05-02T14:26:03+00:00",
  "updated_at": "2026-05-02T14:26:03+00:00",
  "resolved_at": null
}
```

**Query Parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `auto_classify` | Boolean | false | Automatically classify the ticket |

**Example with Auto-Classification**

```bash
curl -X POST "http://localhost:3000/tickets?auto_classify=true" \
  -H "Content-Type: application/json" \
  -d '{"customer_id": "...", ...}'
```

---

#### `GET /tickets` — List Tickets

Retrieve all tickets with optional filtering.

**Request**

```bash
# Get all tickets
curl http://localhost:3000/tickets

# Filter by category
curl "http://localhost:3000/tickets?category=account_access"

# Filter by priority
curl "http://localhost:3000/tickets?priority=urgent"

# Filter by status
curl "http://localhost:3000/tickets?status=new"

# Combine filters
curl "http://localhost:3000/tickets?category=account_access&priority=urgent&status=new"
```

**Query Parameters**

| Parameter | Type | Values | Description |
|-----------|------|--------|-------------|
| `category` | String | account_access, technical_issue, billing_question, feature_request, bug_report, other | Filter by category |
| `priority` | String | urgent, high, medium, low | Filter by priority |
| `status` | String | new, in_progress, waiting_customer, resolved, closed | Filter by status |

**Response (200 OK)**

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "customer_id": "CUST001",
    "customer_email": "john@example.com",
    "customer_name": "John Doe",
    "subject": "Cannot log in to account",
    "description": "I am unable to log into my account...",
    "category": "account_access",
    "priority": "high",
    "status": "new",
    "assigned_to": null,
    "tags": ["urgent", "security"],
    "metadata": {...},
    "classification": {...},
    "created_at": "2026-05-02T14:26:03+00:00",
    "updated_at": "2026-05-02T14:26:03+00:00",
    "resolved_at": null
  },
  {
    "id": "660f9501-f40c-52e5-cgg7-ss7666551111",
    ...
  }
]
```

---

#### `GET /tickets/{id}` — Get Ticket

Retrieve a specific ticket by ID.

**Request**

```bash
curl http://localhost:3000/tickets/550e8400-e29b-41d4-a716-446655440000
```

**Response (200 OK)**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customer_id": "CUST001",
  "customer_email": "john@example.com",
  "customer_name": "John Doe",
  "subject": "Cannot log in to account",
  "description": "I am unable to log into my account...",
  "category": "account_access",
  "priority": "high",
  "status": "new",
  "assigned_to": null,
  "tags": ["urgent", "security"],
  "metadata": {...},
  "classification": {...},
  "created_at": "2026-05-02T14:26:03+00:00",
  "updated_at": "2026-05-02T14:26:03+00:00",
  "resolved_at": null
}
```

**Response (404 Not Found)**

```json
{
  "error": "Not found"
}
```

---

#### `PUT /tickets/{id}` — Update Ticket

Update an existing ticket.

**Request**

```bash
curl -X PUT http://localhost:3000/tickets/550e8400-e29b-41d4-a716-446655440000 \
  -H "Content-Type: application/json" \
  -d '{
    "status": "in_progress",
    "assigned_to": "support@company.com",
    "priority": "urgent"
  }'
```

**Request Body** (all fields optional; provide only fields to update)

| Field | Type | Notes |
|-------|------|-------|
| `customer_id` | String | Update customer identifier |
| `customer_email` | String | Update email address |
| `customer_name` | String | Update name |
| `subject` | String | Update subject |
| `description` | String | Update description |
| `category` | String | Change category |
| `priority` | String | Change priority |
| `status` | String | Change status |
| `assigned_to` | String | Assign to agent |
| `tags` | Array | Update tags |
| `metadata` | Object | Update metadata |

**Response (200 OK)**

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "customer_id": "CUST001",
  "customer_email": "john@example.com",
  "customer_name": "John Doe",
  "subject": "Cannot log in to account",
  "description": "I am unable to log into my account...",
  "category": "account_access",
  "priority": "urgent",
  "status": "in_progress",
  "assigned_to": "support@company.com",
  "tags": ["urgent", "security"],
  "metadata": {...},
  "classification": {...},
  "created_at": "2026-05-02T14:26:03+00:00",
  "updated_at": "2026-05-02T14:26:11+00:00",
  "resolved_at": null
}
```

---

#### `DELETE /tickets/{id}` — Delete Ticket

Delete a ticket permanently.

**Request**

```bash
curl -X DELETE http://localhost:3000/tickets/550e8400-e29b-41d4-a716-446655440000
```

**Response (204 No Content)**

```
(empty body)
```

---

### Bulk Import

#### `POST /tickets/import` — Import Tickets

Bulk import tickets from CSV, JSON, or XML files.

**Supported Formats**

- **CSV** (`text/csv`) — Comma-separated values
- **JSON** (`application/json`) — JSON array of objects
- **XML** (`application/xml`) — XML elements

**Request (CSV)**

```bash
curl -X POST http://localhost:3000/tickets/import \
  -H "Content-Type: text/csv" \
  --data-binary @sample_tickets.csv
```

**Request (JSON)**

```bash
curl -X POST http://localhost:3000/tickets/import \
  -H "Content-Type: application/json" \
  --data-binary @sample_tickets.json
```

**Request (XML)**

```bash
curl -X POST http://localhost:3000/tickets/import \
  -H "Content-Type: application/xml" \
  --data-binary @sample_tickets.xml
```

**CSV Format Example**

```csv
customer_id,customer_email,customer_name,subject,description,category,priority,status,assigned_to,tags,source,browser,device_type
CUST001,john@example.com,John Doe,Login issue,Cannot log in,account_access,high,new,,urgent,web_form,Chrome,desktop
CUST002,jane@example.com,Jane Smith,Billing question,Invoice question,billing_question,medium,new,,billing,api,Safari,mobile
```

**JSON Format Example**

```json
[
  {
    "customer_id": "CUST001",
    "customer_email": "john@example.com",
    "customer_name": "John Doe",
    "subject": "Login issue",
    "description": "Cannot log in to my account",
    "category": "account_access",
    "priority": "high",
    "status": "new",
    "tags": ["urgent"]
  },
  {
    "customer_id": "CUST002",
    "customer_email": "jane@example.com",
    "customer_name": "Jane Smith",
    "subject": "Billing question",
    "description": "Question about my invoice",
    "category": "billing_question",
    "priority": "medium",
    "status": "new",
    "tags": ["billing"]
  }
]
```

**XML Format Example**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<tickets>
  <ticket>
    <customer_id>CUST001</customer_id>
    <customer_email>john@example.com</customer_email>
    <customer_name>John Doe</customer_name>
    <subject>Login issue</subject>
    <description>Cannot log in to my account</description>
    <category>account_access</category>
    <priority>high</priority>
    <status>new</status>
    <tags>urgent</tags>
  </ticket>
  <ticket>
    <customer_id>CUST002</customer_id>
    <customer_email>jane@example.com</customer_email>
    <customer_name>Jane Smith</customer_name>
    <subject>Billing question</subject>
    <description>Question about my invoice</description>
    <category>billing_question</category>
    <priority>medium</priority>
    <status>new</status>
    <tags>billing</tags>
  </ticket>
</tickets>
```

**Response (200 OK)**

```json
{
  "total": 50,
  "successful": 48,
  "failed": 2,
  "errors": [
    {
      "row": 5,
      "field": "customer_email",
      "message": "Invalid email format",
      "raw": {
        "customer_id": "CUST005",
        "customer_email": "invalid-email",
        "customer_name": "John Doe",
        ...
      }
    },
    {
      "row": 12,
      "field": "description",
      "message": "Must be at least 10 characters",
      "raw": {
        "customer_id": "CUST012",
        "customer_email": "jane@example.com",
        "customer_name": "Jane Smith",
        ...
      }
    }
  ]
}
```

**Import Summary Response Fields**

| Field | Type | Description |
|-------|------|-------------|
| `total` | Integer | Total records in import file |
| `successful` | Integer | Successfully created tickets |
| `failed` | Integer | Records that failed validation |
| `errors` | Array | Detailed error information |
| `errors[].row` | Integer | Row number in source file |
| `errors[].field` | String | Field name with error |
| `errors[].message` | String | Error message |
| `errors[].raw` | Object | Original row data |

**Query Parameters**

| Parameter | Type | Values | Description |
|-----------|------|--------|-------------|
| `format` | String | csv, json, xml | Explicit format (auto-detected from Content-Type if omitted) |

---

### Auto-Classification

#### `POST /tickets/{id}/auto-classify` — Auto-Classify Ticket

Automatically categorize and assign priority to a ticket based on its content.

**Request**

```bash
curl -X POST http://localhost:3000/tickets/550e8400-e29b-41d4-a716-446655440000/auto-classify
```

**Response (200 OK)**

```json
{
  "category": "account_access",
  "priority": "urgent",
  "confidence": 0.8333333333333334,
  "reasoning": "Matched account_access via [login, password]",
  "keywords": ["login", "password"]
}
```

**Response Fields**

| Field | Type | Description |
|-------|------|-------------|
| `category` | String | Suggested category based on keyword matching |
| `priority` | String | Suggested priority based on keyword matching |
| `confidence` | Float | Confidence score (0.0 to 1.0) = matched keywords / total keyword combinations |
| `reasoning` | String | "Matched {category} via [{keywords}]" or "priority={priority} via [{keywords}]" |
| `keywords` | Array | Keywords found in ticket subject/description that matched |

**Classification Algorithm**

The classifier analyzes:
1. **Subject** — Ticket title for keywords
2. **Description** — Full text analysis
3. **Tags** — Custom tags provided by user

Keywords are matched against predefined patterns for each category and priority level. Higher keyword matches increase confidence score.

---

## Rate Limiting

Currently, the API has **no rate limiting**. In production, implement rate limiting (e.g., 100 requests/minute per IP).

---

## Versioning

This is **API v1.0**.

---

## Support & Troubleshooting

### Common Issues

**Issue**: `Internal server error` on import
- **Solution**: Verify file format matches Content-Type header (e.g., CSV file with `text/csv`)

**Issue**: `404 Not found` on GET ticket
- **Solution**: Verify ticket ID is correct; check list endpoint for valid IDs

**Issue**: `Validation failed` with empty details
- **Solution**: Check that required fields are provided with valid values

### Getting Help

1. Check the [README.md](../README.md) for project overview
2. Review the [TESTING_GUIDE.md](../docs/TESTING_GUIDE.md) for testing examples
3. Check Docker logs: `docker compose logs php`

---

<div align="center">

**API Reference — v1.0**  
*Last Updated: May 2026*

</div>
