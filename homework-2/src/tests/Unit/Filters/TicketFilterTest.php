<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\TicketFilter;
use PHPUnit\Framework\TestCase;

class TicketFilterTest extends TestCase
{
    public function test_from_params_with_empty_array_uses_defaults(): void
    {
        $filter = TicketFilter::fromParams([]);

        $this->assertSame(50, $filter->limit);
        $this->assertSame(0, $filter->offset);
        $this->assertNull($filter->category);
        $this->assertNull($filter->priority);
        $this->assertNull($filter->status);
        $this->assertNull($filter->customerId);
        $this->assertNull($filter->assignedTo);
        $this->assertNull($filter->q);
        $this->assertNull($filter->sort);
    }

    public function test_from_params_maps_all_supported_parameters(): void
    {
        $filter = TicketFilter::fromParams([
            'category' => 'technical_issue',
            'priority' => 'high',
            'status' => 'new',
            'customer_id' => 'cust-1',
            'assigned_to' => 'agent-1',
            'q' => 'search term',
            'limit' => '10',
            'offset' => '5',
            'sort' => '-created_at',
        ]);

        $this->assertSame('technical_issue', $filter->category);
        $this->assertSame('high', $filter->priority);
        $this->assertSame('new', $filter->status);
        $this->assertSame('cust-1', $filter->customerId);
        $this->assertSame('agent-1', $filter->assignedTo);
        $this->assertSame('search term', $filter->q);
        $this->assertSame(10, $filter->limit);
        $this->assertSame(5, $filter->offset);
        $this->assertSame('-created_at', $filter->sort);
    }

    public function test_limit_clamped_to_200_maximum(): void
    {
        $filter = TicketFilter::fromParams(['limit' => '500']);

        $this->assertSame(200, $filter->limit);
    }

    public function test_limit_clamped_to_1_minimum(): void
    {
        $filterZero = TicketFilter::fromParams(['limit' => '0']);
        $this->assertSame(1, $filterZero->limit);

        $filterNegative = TicketFilter::fromParams(['limit' => '-5']);
        $this->assertSame(1, $filterNegative->limit);
    }

    public function test_negative_offset_clamped_to_zero(): void
    {
        $filter = TicketFilter::fromParams(['offset' => '-10']);

        $this->assertSame(0, $filter->offset);
    }
}
