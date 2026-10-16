<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;


        protected $hidden = [
            'password',
            'remember_token',
        ];

        protected $fillable = ['name', 'email', 'password', 'role', 'is_available', 'last_assigned_at'];

        // replace casts()
        protected function casts(): array
        {
            return [
                'email_verified_at' => 'datetime',
                'password' => 'hashed',
                'role' => Role::class,
                'is_available' => 'boolean',
                'last_assigned_at' => 'datetime',
            ];
        }

        public function isAdmin(): bool    { return $this->role === Role::Admin; }
        public function isAgent(): bool    { return $this->role === Role::Agent; }
        public function isCustomer(): bool { return $this->role === Role::Customer; }
        public function isStaff(): bool    { return $this->isAdmin() || $this->isAgent(); }

        public function assignedTickets(): HasMany
        {
            return $this->hasMany(Ticket::class, 'assigned_agent_id');
        }

        public function scopeAdmins(Builder $query): Builder
        {
            return $query->where('role', Role::Admin->value);
        }

}
