<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Ticket;
use App\Models\User;
use App\Jobs\ProcessTicketImport;
use App\Services\TicketImporter;
use Database\Seeders\SlaPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketImportTest extends TestCase
{
    use RefreshDatabase;

    private function runImport(string $csv): ImportBatch
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/test.csv', $csv);

        $batch = ImportBatch::create([
            'user_id' => User::factory()->admin()->create()->id,
            'original_name' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'status' => 'pending',
        ]);

        (new ProcessTicketImport($batch->id))->handle(app(TicketImporter::class));

        return $batch->refresh();
    }

    public function test_valid_rows_are_imported_invalid_rows_reported_and_duplicates_skipped(): void
    {
        $this->seed(SlaPolicySeeder::class);
        Category::create(['name' => 'Billing']);
        $customer = User::factory()->customer()->create(['email' => 'c@example.com']);

        $csv = implode("\n", [
            'external_ref,subject,description,priority,category,customer_email,created_at',
            'EXT-1,Printer broken,Does not print,high,Billing,c@example.com,2026-10-01 10:00:00',   // record 2: ok
            'EXT-2,Login fails,Cannot login,low,Billing,c@example.com,',                              // record 3: ok
            'EXT-3,Bad priority,Oops,urgent,Billing,c@example.com,',                                  // record 4: invalid priority
            'EXT-1,Duplicate in file,Dup,low,Billing,c@example.com,',                                 // record 5: duplicate ref
            'EXT-4,Unknown customer,Hi,low,Billing,nobody@example.com,',                              // record 6: no such customer
        ]) . "\n";

        $batch = $this->runImport($csv);

        $this->assertSame('completed', $batch->status);
        $this->assertSame(5, $batch->processed_rows);
        $this->assertSame(2, $batch->imported_rows);
        $this->assertSame(1, $batch->skipped_rows);
        $this->assertSame(2, $batch->failed_rows);

        $this->assertDatabaseCount('tickets', 2);
        $this->assertDatabaseHas('tickets', ['external_ref' => 'EXT-1', 'customer_id' => $customer->id, 'status' => 'open']);
        $this->assertDatabaseHas('import_errors', ['import_batch_id' => $batch->id, 'row_number' => 4]);
        $this->assertDatabaseHas('import_errors', ['import_batch_id' => $batch->id, 'row_number' => 6]);

        // every imported ticket has a number and a history entry
        $this->assertSame(2, Ticket::whereNotNull('ticket_number')->count());
        $this->assertDatabaseCount('ticket_activities', 2);
    }

    public function test_rows_whose_external_ref_already_exists_in_the_database_are_skipped(): void
    {
        $this->seed(SlaPolicySeeder::class);
        Category::create(['name' => 'Billing']);
        User::factory()->customer()->create(['email' => 'c@example.com']);
        Ticket::factory()->create(['external_ref' => 'EXT-1']);

        $csv = implode("\n", [
            'external_ref,subject,description,priority,category,customer_email',
            'EXT-1,Already there,Dup,low,Billing,c@example.com',
        ]) . "\n";

        $batch = $this->runImport($csv);

        $this->assertSame(0, $batch->imported_rows);
        $this->assertSame(1, $batch->skipped_rows);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_a_file_without_the_required_columns_fails_the_batch(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->runImport("foo,bar\n1,2\n");
    }
}