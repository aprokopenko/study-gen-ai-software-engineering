<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class ImportTicketTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 10.1
     */
    public function test_import_valid_csv_returns_success_summary(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.csv');

        $response = $this->postRaw('/tickets/import', $content, 'text/csv');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame($data['total'], $data['successful']);
        $this->assertSame(0, $data['failed']);
    }

    /**
     * Req 10.2
     */
    public function test_import_valid_json_returns_success_summary(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.json');

        $response = $this->postRaw('/tickets/import', $content, 'application/json');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame($data['total'], $data['successful']);
        $this->assertSame(0, $data['failed']);
    }

    /**
     * Req 10.3
     */
    public function test_import_valid_xml_returns_success_summary(): void
    {
        $content = file_get_contents(__DIR__ . '/../../fixtures/valid/sample_tickets.xml');

        $response = $this->postRaw('/tickets/import', $content, 'application/xml');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame($data['total'], $data['successful']);
        $this->assertSame(0, $data['failed']);
    }

    /**
     * Req 10.4
     */
    public function test_import_with_invalid_rows_returns_partial_failure(): void
    {
        $csvContent = implode("\n", [
            'customer_id,customer_email,customer_name,subject,description,category,priority',
            'cust-valid,valid@example.com,Valid User,Valid subject,A sufficiently long description for validation.,technical_issue,medium',
            ',not-an-email,,,,invalid_cat,invalid_pri',
        ]);

        $response = $this->postRaw('/tickets/import', $csvContent, 'text/csv');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertGreaterThan(0, $data['failed']);
        $this->assertIsArray($data['errors']);
        $this->assertNotEmpty($data['errors']);
    }

    /**
     * Req 10.5
     */
    public function test_import_unsupported_content_type_returns_error(): void
    {
        $response = $this->postRaw('/tickets/import', 'some content', 'text/plain');

        $this->assertNotSame(200, $response->getStatusCode());
    }
}
