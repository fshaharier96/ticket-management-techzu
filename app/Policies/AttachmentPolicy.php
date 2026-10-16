<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        $attachment->loadMissing(['ticket', 'reply']);

        // Files attached to an internal note are staff-only, even if the customer owns the ticket.
        if ($user->isCustomer() && $attachment->reply?->is_internal) {
            return false;
        }

        return $user->can('view', $attachment->ticket);
    }
}