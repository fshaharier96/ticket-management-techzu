<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'ticket_id', 'ticket_reply_id', 'uploaded_by',
        'path', 'original_name', 'mime_type', 'size',
    ];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function reply(): BelongsTo { return $this->belongsTo(TicketReply::class, 'ticket_reply_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}