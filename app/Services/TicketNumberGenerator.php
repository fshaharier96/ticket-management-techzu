<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    /**
     * Must be called inside the transaction that creates the ticket,
     * so a rollback doesn't leave a gap and the row lock is held until commit.
     */
    public function next(): string
    {
        return DB::transaction(function () {
            $year = (int) now()->year;

            DB::table('ticket_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $row = DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = $row->last_number + 1;

            DB::table('ticket_sequences')->where('year', $year)->update(['last_number' => $next]);

            return sprintf('TKT-%d-%06d', $year, $next); // grows past 6 digits automatically
        });
    }
}