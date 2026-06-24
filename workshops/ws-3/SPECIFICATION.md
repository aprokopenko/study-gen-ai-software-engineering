# Specification Template for KYC API

> Ingest the information from this file, implement the Low-Level Tasks, and generate the code that will satisfy the High and Mid-Level Objectives.

## High-Level Objective
- Build a comprehensive KYC (Know Your Customer) API that validates customer information, verifies documents, and performs compliance checks for banking applications

## Mid-Level Objectives
- Create a complete OpenAPI 3.1 specification for the KYC API
- Implement FastAPI endpoints with proper validation and error handling
- Build document verification capabilities with mock services
- Add compliance checking for sanctions lists and AML requirements
- Implement comprehensive contract testing to validate API against specification
- Include proper authentication, authorization, and audit logging

## Implementation Notes
- Use OpenAPI 3.1 as the source of truth for API design
- Implement with FastAPI framework for automatic documentation
- Use Pydantic models for request/response validation
- Include comprehensive error handling with proper HTTP status codes
- Add authentication using JWT tokens
- Implement audit logging for all API operations
- Follow banking industry standards for data validation
- Include data privacy compliance (GDPR, CCPA)
- Use Decimal for all monetary calculations
- Add comprehensive testing with contract validation
- Follow Python 3.12+ with type hints for better code clarity
- Follow PEP 8 coding standards
- Include logging for audit trails (important for banking)
- Implement proper exception handling for API operations
- Support international compliance requirements
- Include data privacy compliance for PII handling

## Context

### Beginning context
- `README_3_spec_driven.md` - Workshop introduction and learning objectives
- `agents.md` - Agent-based development patterns and examples
- `prompts.md` - Prompt engineering techniques and templates
- `rules_example.md` - Example of specification-driven development rules
- `rules_example2.md` - Advanced specification patterns and best practices
- `SPECIFICATION.md` - This specification document

### Ending context
- `specs/kyc-api.yaml` - Complete OpenAPI 3.1 specification
- `src/models.py` - Pydantic models for data validation
- `src/api.py` - FastAPI application with all endpoints
- `src/compliance_checker.py` - Compliance checking logic
- `src/document_verifier.py` - Document verification services
- `src/auth.py` - Authentication and authorization
- `tests/test_kyc_api.py` - Comprehensive test suite
- `tests/contract_tests.py` - Contract testing with schemathesis
- `docs/api-documentation.md` - API documentation
- `requirements.txt` - Project dependencies
- `README.md` - Installation and usage instructions

## Low-Level Tasks

### 1. Create OpenAPI Specification
```
What prompt would you run to complete this task?
CREATE specs/kyc-api.yaml with complete OpenAPI 3.1 specification

What file do you want to CREATE or UPDATE?
specs/kyc-api.yaml

What function do you want to CREATE or UPDATE?
Complete API specification with all endpoints

What are details you want to add to drive the code changes?
- Define all KYC API endpoints (customers, documents, verification, compliance)
- Include proper HTTP methods, status codes, and error responses
- Define request/response schemas with validation rules
- Add authentication and security requirements
- Include examples for all endpoints
- Follow RESTful design principles
```

### 2. Create Data Models
```
What prompt would you run to complete this task?
CREATE src/models.py with Pydantic models for KYC data

What file do you want to CREATE or UPDATE?
src/models.py

What function do you want to CREATE or UPDATE?
Pydantic models: Customer, Document, VerificationResult, ComplianceCheck

What are details you want to add to drive the code changes?
- Customer model with KYC fields (name, DOB, address, phone, email)
- Document model for uploaded documents
- VerificationResult model for verification outcomes
- ComplianceCheck model for compliance status
- Include proper validation and error messages
- Add data privacy and security considerations
```

### 3. Implement FastAPI Application
```
What prompt would you run to complete this task?
CREATE src/api.py with FastAPI application and all endpoints

What file do you want to CREATE or UPDATE?
src/api.py

What function do you want to CREATE or UPDATE?
FastAPI app with endpoints: POST /customers, GET /customers/{id}, POST /customers/{id}/documents, POST /customers/{id}/verify, GET /customers/{id}/compliance

What are details you want to add to drive the code changes?
- Implement all endpoints from OpenAPI specification
- Add proper request/response validation using Pydantic models
- Include comprehensive error handling with proper HTTP status codes
- Add authentication and authorization middleware
- Include audit logging for all operations
- Follow FastAPI best practices
```

### 4. Implement Document Verification
```
What prompt would you run to complete this task?
CREATE src/document_verifier.py with document verification logic

What file do you want to CREATE or UPDATE?
src/document_verifier.py

What function do you want to CREATE or UPDATE?
DocumentVerifier class with methods: verify_identity_document(), verify_address_document(), verify_income_document()

What are details you want to add to drive the code changes?
- Implement mock document verification services
- Add validation for different document types (passport, driver's license, utility bills)
- Include OCR simulation for document text extraction
- Add document authenticity checks
- Include error handling for invalid documents
- Add audit logging for verification attempts
```

### 5. Implement Compliance Checking
```
What prompt would you run to complete this task?
CREATE src/compliance_checker.py with compliance checking logic

What file do you want to CREATE or UPDATE?
src/compliance_checker.py

What function do you want to CREATE or UPDATE?
ComplianceChecker class with methods: check_sanctions(), check_pep(), check_aml(), generate_compliance_report()

What are details you want to add to drive the code changes?
- Implement sanctions list checking (OFAC, EU, UN)
- Add PEP (Politically Exposed Person) screening
- Include AML (Anti-Money Laundering) risk assessment
- Add compliance scoring and risk categorization
- Include audit logging for all compliance checks
- Add data privacy compliance validation
```

### 6. Create Contract Tests
```
What prompt would you run to complete this task?
CREATE tests/contract_tests.py with schemathesis contract testing

What file do you want to CREATE or UPDATE?
tests/contract_tests.py

What function do you want to CREATE or UPDATE?
Contract test suite using schemathesis to validate API against OpenAPI spec

What are details you want to add to drive the code changes?
- Use schemathesis to generate test cases from OpenAPI spec
- Test all endpoints, methods, and response codes
- Validate request/response schemas
- Test error handling and edge cases
- Include performance and load testing
- Add test data fixtures for realistic scenarios
```

### 7. Create Integration Tests
```
What prompt would you run to complete this task?
CREATE tests/test_kyc_api.py with comprehensive integration tests

What file do you want to CREATE or UPDATE?
tests/test_kyc_api.py

What function do you want to CREATE or UPDATE?
Integration test suite: TestCustomerAPI, TestDocumentVerification, TestComplianceChecking

What are details you want to add to drive the code changes?
- Test complete KYC workflow end-to-end
- Test document verification with mock services
- Test compliance checking with sample data
- Include error scenarios and edge cases
- Test authentication and authorization
- Add performance tests for API endpoints
- Include security tests for input validation
```

### 8. Add Authentication and Security
```
What prompt would you run to complete this task?
CREATE src/auth.py with JWT authentication and security

What file do you want to CREATE or UPDATE?
src/auth.py

What function do you want to CREATE or UPDATE?
Authentication system with JWT tokens, role-based access control, and security middleware

What are details you want to add to drive the code changes?
- Implement JWT token-based authentication
- Add role-based access control (admin, user, read-only)
- Include password hashing and validation
- Add rate limiting and throttling
- Include security headers and CORS configuration
- Add audit logging for authentication events
- Implement session management and token refresh
```
