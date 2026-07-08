<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\TicketValidator;
use App\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Somnambulist\Components\Validation\Factory;
use Tests\Traits\TicketDataBuilder;

class TicketValidatorTest extends TestCase
{
    use TicketDataBuilder;

    private TicketValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TicketValidator(new Factory());
    }

    // Req 3.1
    public function test_validate_create_passes_with_valid_data(): void
    {
        $this->validator->validateCreate($this->validTicketData());
        $this->addToAssertionCount(1);
    }

    // Req 3.2
    #[DataProvider('requiredFieldsProvider')]
    public function test_validate_create_fails_on_missing_required_fields(string $field): void
    {
        $data = $this->validTicketData();
        unset($data[$field]);

        $this->expectException(ValidationException::class);
        $this->validator->validateCreate($data);
    }

    public static function requiredFieldsProvider(): array
    {
        return [
            'customer_id' => ['customer_id'],
            'customer_email' => ['customer_email'],
            'customer_name' => ['customer_name'],
            'subject' => ['subject'],
            'description' => ['description'],
            'category' => ['category'],
            'priority' => ['priority'],
        ];
    }

    // Req 3.3
    public function test_validate_create_fails_on_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');
        $this->validator->validateCreate($this->validTicketData(['customer_email' => 'not-an-email']));
    }

    // Req 3.4
    public function test_validate_create_fails_on_subject_too_long(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateCreate($this->validTicketData(['subject' => str_repeat('a', 201)]));
    }

    // Req 3.5
    public function test_validate_create_fails_on_description_too_short(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateCreate($this->validTicketData(['description' => 'short']));
    }

    // Req 3.6
    public function test_validate_create_fails_on_invalid_category(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateCreate($this->validTicketData(['category' => 'nonexistent']));
    }

    // Req 3.7
    public function test_validate_create_fails_on_invalid_priority(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateCreate($this->validTicketData(['priority' => 'nonexistent']));
    }

    // Req 3.8
    public function test_validate_update_passes_with_valid_partial_data(): void
    {
        $this->validator->validateUpdate(['subject' => 'New subject']);
        $this->addToAssertionCount(1);
    }

    // Req 3.9
    public function test_validate_update_fails_on_invalid_optional_field(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateUpdate(['customer_email' => 'not-an-email']);
    }
}
