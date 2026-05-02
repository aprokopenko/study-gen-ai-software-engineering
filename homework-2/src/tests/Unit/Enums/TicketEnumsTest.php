<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Ticket\Category;
use App\Enums\Ticket\DeviceType;
use App\Enums\Ticket\Priority;
use App\Enums\Ticket\Source;
use App\Enums\Ticket\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketEnumsTest extends TestCase
{
    public function test_category_enum_has_six_cases(): void
    {
        $cases = Category::cases();

        $this->assertSame(6, count($cases));

        $values = array_column($cases, 'value');
        $this->assertSame(
            ['account_access', 'technical_issue', 'billing_question', 'feature_request', 'bug_report', 'other'],
            $values
        );
    }

    public function test_priority_enum_has_four_cases(): void
    {
        $cases = Priority::cases();

        $this->assertSame(4, count($cases));

        $values = array_column($cases, 'value');
        $this->assertSame(
            ['urgent', 'high', 'medium', 'low'],
            $values
        );
    }

    public function test_status_enum_has_five_cases(): void
    {
        $cases = Status::cases();

        $this->assertSame(5, count($cases));

        $values = array_column($cases, 'value');
        $this->assertSame(
            ['new', 'in_progress', 'waiting_customer', 'resolved', 'closed'],
            $values
        );
    }

    public function test_source_enum_has_five_cases(): void
    {
        $cases = Source::cases();

        $this->assertSame(5, count($cases));

        $values = array_column($cases, 'value');
        $this->assertSame(
            ['web_form', 'email', 'api', 'chat', 'phone'],
            $values
        );
    }

    public function test_device_type_enum_has_four_cases(): void
    {
        $cases = DeviceType::cases();

        $this->assertSame(4, count($cases));

        $values = array_column($cases, 'value');
        $this->assertSame(
            ['desktop', 'mobile', 'tablet', 'other'],
            $values
        );
    }

    #[DataProvider('invalidEnumProvider')]
    public function test_from_with_invalid_value_throws_value_error(string $enumClass): void
    {
        $this->expectException(\ValueError::class);

        $enumClass::from('invalid');
    }

    #[DataProvider('invalidEnumProvider')]
    public function test_try_from_with_invalid_value_returns_null(string $enumClass): void
    {
        $result = $enumClass::tryFrom('invalid');

        $this->assertNull($result);
    }

    public static function invalidEnumProvider(): array
    {
        return [
            'Category' => [Category::class],
            'Priority' => [Priority::class],
            'Status' => [Status::class],
            'Source' => [Source::class],
            'DeviceType' => [DeviceType::class],
        ];
    }
}
