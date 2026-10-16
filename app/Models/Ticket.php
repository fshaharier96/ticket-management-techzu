<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'external_ref', 'subject', 'description', 'priority', 'status',
        'category_id', 'customer_id', 'assigned_agent_id',
        'sla_due_at', 'sla_warned_at', 'sla_breached_at', 'escalated_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'sla_warned_at' => 'datetime',
            'sla_breached_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /* ---------- Relationships ---------- */

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(User::class, 'assigned_agent_id'); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function replies(): HasMany { return $this->hasMany(TicketReply::class); }
    public function attachments(): HasMany { return $this->hasMany(Attachment::class); }
    public function activities(): HasMany { return $this->hasMany(TicketActivity::class); }

    /* ---------- State scopes ---------- */

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', TicketStatus::activeValues());
    }

    public function scopeFinished(Builder $q): Builder
    {
        return $q->whereIn('status', TicketStatus::finishedValues());
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        // Customers only ever see their own tickets; staff see all (agents need the unassigned queue).
        return $user->isCustomer() ? $q->where('customer_id', $user->id) : $q;
    }

    /* ---------- SLA scopes (all use the (status, sla_due_at) index) ---------- */

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

    /* ---------- Search & filtering ---------- */

    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['q'] ?? null, function (Builder $q, string $term) {
                $term = trim($term);

                if (stripos($term, 'TKT-') === 0) {
                    // prefix search → can use the unique index on ticket_number
                    $q->where('ticket_number', 'like', addcslashes($term, '%_\\') . '%');
                } else {
                    $like = '%' . addcslashes($term, '%_\\') . '%';
                    $q->where(fn (Builder $w) => $w->where('subject', 'like', $like)->orWhere('description', 'like', $like));
                }
            })
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($f['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($f['category_id'] ?? null, fn (Builder $q, $v) => $q->where('category_id', $v))
            ->when($f['agent'] ?? null, fn (Builder $q, $v) => $v === 'unassigned'
                ? $q->whereNull('assigned_agent_id')
                : $q->where('assigned_agent_id', $v))
            ->when($f['customer'] ?? null, function (Builder $q, string $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($f['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($f['sla'] ?? null, fn (Builder $q, $v) => match ($v) {
                'breached' => $q->slaBreached(),
                'approaching' => $q->slaApproaching(),
                'within' => $q->slaWithin(),
                default => $q,
            });
    }

    /* ---------- Display helper ---------- */

    /** within | approaching | breached | met (finished on time). Lists filter with the scopes above. */
    public function slaStatus(): string
    {
        if ($this->status->isFinished()) {
            return ($this->resolved_at && $this->sla_due_at && $this->resolved_at->gt($this->sla_due_at))
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