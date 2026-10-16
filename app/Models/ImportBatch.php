<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id', 'original_name', 'file_path', 'status',
        'processed_rows', 'imported_rows', 'skipped_rows', 'failed_rows',
        'error_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function errors(): HasMany { return $this->hasMany(ImportError::class); }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}