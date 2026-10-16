<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTicketImport;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('imports.index', [
            'batches' => ImportBatch::with('user:id,name')->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'], // 50 MB
        ]);

        $file = $request->file('file');

        $batch = ImportBatch::create([
            'user_id' => $request->user()->id,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'file_path' => $file->store('imports', 'local'),
            'status' => 'pending',
        ]);

        ProcessTicketImport::dispatch($batch->id); // returns immediately; a worker does the heavy lifting

        return redirect()->route('imports.show', $batch)
            ->with('success', 'Import queued. This page refreshes while it runs.');
    }

    public function show(ImportBatch $batch): View
    {
        return view('imports.show', [
            'batch' => $batch,
            'rowErrors' => $batch->errors()->orderBy('row_number')->paginate(20),
        ]);
    }

    /** Streams the invalid rows as CSV without loading them all into memory. */
    public function errors(ImportBatch $batch): StreamedResponse
    {
        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row_number', 'errors', 'row_data'], ',', '"', '\\');

            foreach ($batch->errors()->orderBy('row_number')->cursor() as $error) {
                fputcsv($out, [
                    $error->row_number,
                    collect($error->errors)->flatten()->implode('; '),
                    json_encode($error->row_data, JSON_UNESCAPED_UNICODE),
                ], ',', '"', '\\');
            }

            fclose($out);
        }, "import-{$batch->id}-errors.csv", ['Content-Type' => 'text/csv']);
    }
}