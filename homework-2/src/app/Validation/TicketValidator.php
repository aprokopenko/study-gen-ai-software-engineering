<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\Ticket\Category;
use App\Enums\Ticket\DeviceType;
use App\Enums\Ticket\Priority;
use App\Enums\Ticket\Source;
use App\Enums\Ticket\Status;
use Carbon\Carbon;
use Somnambulist\Components\Validation\Factory;

class TicketValidator
{
    public function __construct(private readonly Factory $factory) {}

    public function validateCreate(array $data): void
    {
        $this->run($data, array_merge($this->commonRules(), [
            'customer_id' => 'required',
            'customer_email' => 'required|email',
            'customer_name' => 'required',
            'subject' => 'required|between:1,200',
            'description' => 'required|between:10,2000',
            'category' => 'required|in:' . $this->enumValues(Category::class),
            'priority' => 'required|in:' . $this->enumValues(Priority::class),
        ]));
    }

    public function validateUpdate(array $data): void
    {
        $this->run($data, array_merge($this->commonRules(), [
            'customer_email' => 'email',
            'subject' => 'between:1,200',
            'description' => 'between:10,2000',
            'category' => 'in:' . $this->enumValues(Category::class),
            'priority' => 'in:' . $this->enumValues(Priority::class),
        ]));
    }

    public function validateImportRow(array $data): void
    {
        $this->validateCreate($data);
    }

    private function run(array $data, array $rules): void
    {
        $validation = $this->factory->validate($data, $rules);

        if ($validation->fails()) {
            /** @var array<string,string> $errors */
            $errors = $validation->errors()->firstOfAll(':message', true);
            throw new ValidationException($errors);
        }
    }

    private function commonRules(): array
    {
        $isoDate = function (mixed $value): bool {
            if (!is_string($value)) {
                return false;
            }
            try {
                Carbon::parse($value);
                return true;
            } catch (\Throwable) {
                return false;
            }
        };

        return [
            'status' => 'in:' . $this->enumValues(Status::class),
            'metadata.source' => 'in:' . $this->enumValues(Source::class),
            'metadata.device_type' => 'in:' . $this->enumValues(DeviceType::class),
            'tags' => 'array',
            'tags.*' => 'max:50',
            'created_at' => [$isoDate],
            'updated_at' => [$isoDate],
            'resolved_at' => [$isoDate],
        ];
    }

    private function enumValues(string $enumClass): string
    {
        return implode(',', array_column($enumClass::cases(), 'value'));
    }
}
