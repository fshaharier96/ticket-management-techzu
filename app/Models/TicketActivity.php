<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    public $timestamps = false;

    public const CREATED = 'created';
    public const ASSIGNED = 'assigned';
    public const PRIORITY_CHANGED = 'priority_changed';
    public const STATUS_CHANGED = 'status_changed';
    public const REPLIED = 'replied';
    public const NOTE_ADDED = 'note_added';
    public const ATTACHMENT_ADDED = 'attachment_added';
    public const SLA_APPROACHING = 'sla_approaching';
    public const SLA_BREACHED = 'sla_breached';
    public const ESCALATED = 'escalated';

    protected $fillable = ['ticket_id', 'user_id', 'type', 'old_value', 'new_value', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Human readable line for the timeline. */
    public function description(): string
    {
        $who = $this->user?->name ?? 'System';

        return match ($this->type) {
            self::CREATED => "Ticket created by {$who}",
            self::ASSIGNED => "Assigned to {$this->new_value}" . ($this->old_value ? " (was {$this->old_value})" : '') . " by {$who}",
            self::PRIORITY_CHANGED => "Priority changed from {$this->old_value} to {$this->new_value} by {$who}",
            self::STATUS_CHANGED => "Status changed from {$this->old_value} to {$this->new_value} by {$who}",
            self::REPLIED => "{$who} replied",
            self::NOTE_ADDED => "{$who} added an internal note",
            self::ATTACHMENT_ADDED => "{$who} attached file(s)",
            self::SLA_APPROACHING => 'SLA is approaching its deadline',
            self::SLA_BREACHED => 'SLA breached',
            self::ESCALATED => 'Ticket escalated',
            default => $this->type,
        };
    }
}