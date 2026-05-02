<?php

declare(strict_types=1);

namespace Tests\Traits;

trait TicketDataBuilder
{
    protected function validTicketData(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => 'cust-test-001',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'subject' => 'Test ticket subject',
            'description' => 'A sufficiently long description for validation purposes.',
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'new',
            'tags' => ['test', 'unit'],
            'metadata' => [
                'source' => 'web_form',
                'browser' => 'PHPUnit',
                'device_type' => 'desktop',
            ],
        ], $overrides);
    }

    protected function validTicketRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 'test-uuid-001',
            'customer_id' => 'cust-test-001',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'subject' => 'Test ticket subject',
            'description' => 'A sufficiently long description for validation purposes.',
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'new',
            'assigned_to' => null,
            'tags' => '["test","unit"]',
            'metadata_source' => 'web_form',
            'metadata_browser' => 'PHPUnit',
            'metadata_device_type' => 'desktop',
            'classification_confidence' => null,
            'classification_reasoning' => null,
            'classification_keywords' => null,
            'created_at' => '2024-01-15T10:00:00+00:00',
            'updated_at' => '2024-01-15T10:00:00+00:00',
            'resolved_at' => null,
        ], $overrides);
    }

    protected function minimalTicketData(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => 'cust-test-001',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'subject' => 'Minimal ticket subject',
            'description' => 'A sufficiently long description for validation purposes.',
            'category' => 'technical_issue',
            'priority' => 'medium',
        ], $overrides);
    }
}
