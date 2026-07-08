<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Tests\Concerns\AppTestCase;
use Tests\Traits\TicketDataBuilder;

class ListFilterTest extends AppTestCase
{
    use TicketDataBuilder;

    /**
     * Req 12.1
     */
    public function test_filter_by_category(): void
    {
        $this->postJson('/tickets', $this->validTicketData(['category' => 'technical_issue']));
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'b@example.com',
            'category' => 'billing_question',
        ]));

        $response = $this->get('/tickets?category=technical_issue');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        foreach ($data as $ticket) {
            $this->assertSame('technical_issue', $ticket['category']);
        }
    }

    /**
     * Req 12.2
     */
    public function test_filter_by_priority(): void
    {
        $this->postJson('/tickets', $this->validTicketData(['priority' => 'high']));
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'b@example.com',
            'priority' => 'low',
        ]));

        $response = $this->get('/tickets?priority=high');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);

        foreach ($data as $ticket) {
            $this->assertSame('high', $ticket['priority']);
        }
    }

    /**
     * Req 12.3
     */
    public function test_filter_by_status(): void
    {
        $this->postJson('/tickets', $this->validTicketData(['status' => 'new']));
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'b@example.com',
            'status' => 'resolved',
        ]));

        $response = $this->get('/tickets?status=new');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);

        foreach ($data as $ticket) {
            $this->assertSame('new', $ticket['status']);
        }
    }

    /**
     * Req 12.4
     */
    public function test_search_by_q_parameter(): void
    {
        $this->postJson('/tickets', $this->validTicketData([
            'subject' => 'Unique search term XYZ123',
        ]));
        $this->postJson('/tickets', $this->validTicketData([
            'customer_email' => 'b@example.com',
            'subject' => 'Completely different subject',
        ]));

        $response = $this->get('/tickets?q=XYZ123');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        foreach ($data as $ticket) {
            $subjectMatches = str_contains($ticket['subject'], 'XYZ123');
            $descriptionMatches = str_contains($ticket['description'], 'XYZ123');
            $this->assertTrue(
                $subjectMatches || $descriptionMatches,
                'Returned ticket does not contain search term in subject or description'
            );
        }
    }

    /**
     * Req 12.5
     */
    public function test_pagination_with_limit_and_offset(): void
    {
        $emails = ['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com'];
        foreach ($emails as $email) {
            $this->postJson('/tickets', $this->validTicketData(['customer_email' => $email]));
        }

        $responsePage1 = $this->get('/tickets?limit=2&offset=0');
        $this->assertSame(200, $responsePage1->getStatusCode());
        $page1 = json_decode((string) $responsePage1->getBody(), true);
        $this->assertCount(2, $page1);

        $responsePage2 = $this->get('/tickets?limit=2&offset=2');
        $this->assertSame(200, $responsePage2->getStatusCode());
        $page2 = json_decode((string) $responsePage2->getBody(), true);
        $this->assertCount(2, $page2);

        $page1Ids = array_column($page1, 'id');
        $page2Ids = array_column($page2, 'id');
        $this->assertEmpty(
            array_intersect($page1Ids, $page2Ids),
            'Pages should not share ticket IDs'
        );
    }

    /**
     * Req 12.6
     */
    public function test_sort_by_created_at_descending(): void
    {
        $this->postJson('/tickets', $this->validTicketData(['customer_email' => 'a@example.com']));
        $this->postJson('/tickets', $this->validTicketData(['customer_email' => 'b@example.com']));
        $this->postJson('/tickets', $this->validTicketData(['customer_email' => 'c@example.com']));

        $response = $this->get('/tickets?sort=-created_at');

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        $first = $data[0]['created_at'];
        $second = $data[1]['created_at'];
        $this->assertGreaterThanOrEqual(
            0,
            strcmp($first, $second),
            'First ticket created_at should be >= second ticket created_at (descending order)'
        );
    }
}
