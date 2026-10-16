<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            // staff create tickets on behalf of a customer; customers always create for themselves
            'customer_id' => [
                Rule::requiredIf(! $this->user()->isCustomer()),
                Rule::exists('users', 'id')->where('role', Role::Customer->value),
            ],
            ...AttachmentRules::rules(),
        ];
    }
}