<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    public $timestamps = false;

    protected $fillable = ['import_batch_id', 'row_number', 'row_data', 'errors', 'created_at'];

    protected function casts(): array
    {
        return ['row_data' => 'array', 'errors' => 'array', 'created_at' => 'datetime'];
    }
}