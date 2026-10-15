<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'subject', 'description', 'priority', 'status',
        'category_id', 'customer_id', 'assigned_agent_id',
        'sla_due_at', 'sla_breached_at', 'escalated_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // Relationships
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function agent(): BelongsTo    { return $this->belongsTo(User::class, 'assigned_agent_id'); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function replies(): HasMany    { return $this->hasMany(TicketReply::class); }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class); }
    public function activities(): HasMany { return $this->hasMany(TicketActivity::class); }

    // Scopes
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', TicketStatus::activeValues());
    }

    public function scopeFinished(Builder $q): Builder
    {
        return $q->whereIn('status', TicketStatus::finishedValues());
    }

    public function scopeUnassigned(Builder $q): Builder
    {
        return $q->whereNull('assigned_agent_id');
    }

    public function scopeSlaBreached(Builder $q): Builder
    {
        return $q->active()->where('sla_due_at', '<', now());
    }

    public function scopeSlaApproaching(Builder $q): Builder
    {
        return $q->active()->whereBetween('sla_due_at', [
            now(), now()->addMinutes(config('sla.approaching_minutes')),
        ]);
    }

    public function scopeSlaWithin(Builder $q): Builder
    {
        return $q->active()->where('sla_due_at', '>', now()->addMinutes(config('sla.approaching_minutes')));
    }

    // SLA status for display (list filters use the scopes above)
    public function slaStatus(): string
    {
        if ($this->status->isFinished()) {
            return $this->resolved_at && $this->sla_due_at && $this->resolved_at->gt($this->sla_due_at)
                ? 'breached' : 'met';
        }

        if ($this->sla_due_at === null) {
            return 'within';
        }
        if ($this->sla_due_at->isPast()) {
            return 'breached';
        }
        if ($this->sla_due_at->lte(now()->addMinutes(config('sla.approaching_minutes')))) {
            return 'approaching';
        }

        return 'within';
    }
}