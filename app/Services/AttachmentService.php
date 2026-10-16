<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * @param  UploadedFile[]  $files  already validated by AttachmentRules
     * @return Attachment[]
     */
    public function storeMany(Ticket $ticket, User $uploader, array $files, ?TicketReply $reply = null): array
    {
        $disk = config('tickets.attachments.disk');
        $saved = [];

        foreach ($files as $file) {
            $size = $file->getSize();
            $mime = $file->getMimeType() ?: 'application/octet-stream'; // detected from content

            // store() generates a random hashed file name, so the original name never touches the filesystem
            $path = $file->store("tickets/{$ticket->id}", $disk);

            $saved[] = Attachment::create([
                'ticket_id' => $ticket->id,
                'ticket_reply_id' => $reply?->id,
                'uploaded_by' => $uploader->id,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime_type' => $mime,
                'size' => $size,
            ]);
        }

        return $saved;
    }
}