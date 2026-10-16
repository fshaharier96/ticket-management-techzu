<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\TicketImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessTicketImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // long-running by design
    public int $tries = 1;      // an import is not retried blindly (it would re-read rows)

    public function __construct(public int $batchId) {}

    public function handle(TicketImporter $importer): void
    {
        $importer->process(ImportBatch::findOrFail($this->batchId));
    }

    public function failed(Throwable $e): void
    {
        ImportBatch::whereKey($this->batchId)->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
            'finished_at' => now(),
        ]);
    }
}