<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $capacities = [2, 2, 4, 4, 4, 4, 6, 6, 8, 4];

        foreach ($capacities as $i => $capacity) {
            DiningTable::updateOrCreate(['number' => $i + 1], ['capacity' => $capacity]);
        }
    }
}
