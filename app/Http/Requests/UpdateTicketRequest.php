<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
        ];

        // Only staff may change priority, category and status
        if ($this->user()->isStaff()) {
            $rules += [
                'priority' => ['required', Rule::enum(TicketPriority::class)],
                'category_id' => ['required', Rule::exists('categories', 'id')],
                'status' => ['required', Rule::enum(TicketStatus::class)],
            ];
        }

        return $rules;
    }
}