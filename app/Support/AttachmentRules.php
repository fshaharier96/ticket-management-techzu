<?php

namespace App\Support;

class AttachmentRules
{
    /** Shared validation rules for any form that accepts attachments. */
    public static function rules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:' . config('tickets.attachments.max_files')],
            'attachments.*' => [
                'file',
                'max:' . config('tickets.attachments.max_kb'),
                // `mimes` checks the type detected from the file CONTENT, not the client-supplied name.
                'mimes:' . implode(',', config('tickets.attachments.mimes')),
            ],
        ];
    }
}