<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        // Business minutes
        $policies = ['low' => 48 * 60, 'medium' => 24 * 60, 'high' => 8 * 60, 'critical' => 2 * 60];

        foreach ($policies as $priority => $minutes) {
            SlaPolicy::updateOrCreate(['priority' => $priority], ['resolution_minutes' => $minutes]);
        }
    }
}