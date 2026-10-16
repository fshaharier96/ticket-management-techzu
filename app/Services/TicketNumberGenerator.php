<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public function next(): string
    {
        return $this->reserve(1)[0];
    }

    /**
     * Reserve a block of numbers (used by the CSV import to avoid one lock per row).
     * When called inside the transaction that creates the ticket, the row lock is held until
     * commit, so a rollback never leaves a gap and two requests can never get the same number.
     *
     * @return string[]
     */
    public function reserve(int $count): array
    {
        if ($count < 1) {
            return [];
        }

        return DB::transaction(function () use ($count) {
            $year = (int) now()->year;

            DB::table('ticket_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $last = (int) DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->value('last_number');

            DB::table('ticket_sequences')->where('year', $year)->update(['last_number' => $last + $count]);

            return array_map(
                fn (int $n) => sprintf('TKT-%d-%06d', $year, $n), // %06d simply grows past 6 digits
                range($last + 1, $last + $count),
            );
        });
    }
}