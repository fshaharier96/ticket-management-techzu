<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\Sla\SlaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use SplFileObject;

class TicketImporter
{
    private const CHUNK_SIZE = 500;
    private const REQUIRED_COLUMNS = ['subject', 'description', 'priority', 'category', 'customer_email'];

    /** @var array<string,int> lower-cased category name => id */
    private array $categories = [];

    public function __construct(
        private TicketNumberGenerator $numbers,
        private SlaService $sla,
    ) {}

    public function process(ImportBatch $batch): void
    {
        $batch->update(['status' => 'processing', 'started_at' => now()]);

        $this->categories = Category::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])
            ->all();

        // SplFileObject reads ONE record at a time → constant memory
        $file = new SplFileObject(Storage::disk('local')->path($batch->file_path));
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        $header = null;
        $chunk = [];
        $seenRefs = []; // external_refs already accepted in THIS file
        $record = 0;

        foreach ($file as $cells) {
            $record++; // header = record 1; this is the number shown in the error report

            if ($cells === false || $cells === [null] || $cells === ['']) {
                continue; // blank line / end of file
            }

            if ($header === null) {
                $header = $this->readHeader($cells);
                continue;
            }

            $chunk[$record] = $cells;

            if (count($chunk) >= self::CHUNK_SIZE) {
                $this->processChunk($batch, $header, $chunk, $seenRefs);
                $chunk = [];
            }
        }

        if ($header === null) {
            throw new RuntimeException('The file is empty.');
        }

        if ($chunk) {
            $this->processChunk($batch, $header, $chunk, $seenRefs);
        }

        $batch->update(['status' => 'completed', 'finished_at' => now()]);
    }

    private function readHeader(array $cells): array
    {
        $header = array_map(
            fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), // strip UTF-8 BOM
            $cells,
        );

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing) {
            throw new RuntimeException('Missing required column(s): ' . implode(', ', $missing));
        }

        return $header;
    }

    /**
     * @param  array<int,array>  $chunk  record number => cells
     */
    private function processChunk(ImportBatch $batch, array $header, array $chunk, array &$seenRefs): void
    {
        $errors = [];
        $skipped = 0;
        $candidates = [];

        // 1) Shape + validation (no database access)
        foreach ($chunk as $record => $cells) {
            $cells = array_map(fn ($c) => trim((string) $c), $cells);

            if (count($cells) !== count($header)) {
                $errors[] = $this->errorRow($batch, $record, $cells, ['row' => ['Column count does not match the header.']]);
                continue;
            }

            $data = array_combine($header, $cells);
            $data['priority'] = strtolower($data['priority']);
            if (isset($data['status'])) {
                $data['status'] = strtolower($data['status']);
            }

            $validator = Validator::make($data, $this->rules());
            if ($validator->fails()) {
                $errors[] = $this->errorRow($batch, $record, $data, $validator->errors()->toArray());
                continue;
            }

            $candidates[$record] = $data;
        }

        // 2) Lookups: one query per chunk, not per row
        $emails = collect($candidates)->pluck('customer_email')->map(fn ($e) => mb_strtolower($e))->unique()->all();
        $customers = $emails
            ? User::whereIn('email', $emails)->where('role', Role::Customer->value)->pluck('id', 'email')
                ->mapWithKeys(fn ($id, $email) => [mb_strtolower($email) => $id])->all()
            : [];

        $refs = collect($candidates)->pluck('external_ref')->filter()->unique()->all();
        $existingRefs = $refs ? array_flip(Ticket::whereIn('external_ref', $refs)->pluck('external_ref')->all()) : [];

        // 3) Build the rows to insert
        $rows = [];
        foreach ($candidates as $record => $data) {
            $customerId = $customers[mb_strtolower($data['customer_email'])] ?? null;
            $categoryId = $this->categories[mb_strtolower($data['category'])] ?? null;

            if (! $customerId || ! $categoryId) {
                $problems = [];
                if (! $customerId) {
                    $problems['customer_email'] = ['No customer exists with this e-mail address.'];
                }
                if (! $categoryId) {
                    $problems['category'] = ['Unknown category.'];
                }
                $errors[] = $this->errorRow($batch, $record, $data, $problems);
                continue;
            }

            $ref = $data['external_ref'] ?? '';
            if ($ref !== '') {
                if (isset($existingRefs[$ref]) || isset($seenRefs[$ref])) {
                    $skipped++; // duplicate: not an error, just skipped
                    continue;
                }
                $seenRefs[$ref] = true;
            }

            $priority = TicketPriority::from($data['priority']);
            $status = ($data['status'] ?? '') !== '' ? TicketStatus::from($data['status']) : TicketStatus::Open;
            $createdAt = ($data['created_at'] ?? '') !== '' ? Carbon::parse($data['created_at']) : now();

            $rows[] = [
                'external_ref' => $ref !== '' ? $ref : null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority->value,
                'status' => $status->value,
                'category_id' => $categoryId,
                'customer_id' => $customerId,
                'sla_due_at' => $this->sla->dueAt($priority, $createdAt),
                'resolved_at' => $status->isFinished() ? now() : null,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ];
        }

        // 4) Persist
        $imported = 0;
        if ($rows) {
            $numbers = $this->numbers->reserve(count($rows)); // one short lock for the whole block
            foreach ($rows as $i => $row) {
                $rows[$i]['ticket_number'] = $numbers[$i];
            }

            DB::transaction(function () use ($rows, $batch, $numbers, &$imported) {
                // insertOrIgnore: if a parallel request grabbed the same external_ref, the unique index
                // makes MySQL skip that row instead of aborting the whole chunk.
                $imported = (int) DB::table('tickets')->insertOrIgnore($rows);

                $ids = Ticket::whereIn('ticket_number', $numbers)->pluck('id'); // only rows that really got inserted
                $now = now();

                DB::table('ticket_activities')->insert(
                    $ids->map(fn ($id) => [
                        'ticket_id' => $id,
                        'user_id' => $batch->user_id,
                        'type' => TicketActivity::CREATED,
                        'old_value' => null,
                        'new_value' => null,
                        'meta' => json_encode(['source' => 'csv_import', 'batch_id' => $batch->id]),
                        'created_at' => $now,
                    ])->all()
                );
            });

            $skipped += count($rows) - $imported; // rows ignored by the unique index (race)
        }

        if ($errors) {
            DB::table('import_errors')->insert($errors);
        }

        // 5) Progress, visible on the status page while the job is still running
        ImportBatch::whereKey($batch->id)->update([
            'processed_rows' => DB::raw('processed_rows + ' . count($chunk)),
            'imported_rows' => DB::raw('imported_rows + ' . $imported),
            'skipped_rows' => DB::raw('skipped_rows + ' . $skipped),
            'failed_rows' => DB::raw('failed_rows + ' . count($errors)),
        ]);
    }

    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'category' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'external_ref' => ['nullable', 'string', 'max:100'],
            'created_at' => ['nullable', 'date'],
        ];
    }

    private function errorRow(ImportBatch $batch, int $record, array $data, array $messages): array
    {
        return [
            'import_batch_id' => $batch->id,
            'row_number' => $record,
            'row_data' => json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
            'errors' => json_encode($messages, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ];
    }
}