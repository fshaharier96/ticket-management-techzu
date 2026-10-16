<?php

namespace App\Http\Requests;

use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
            ...AttachmentRules::rules(),
        ];
    }
}