<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $units = [
            ['name' => 'طن',       'abbreviation' => 'ط'],
            ['name' => 'كيلو',     'abbreviation' => 'ك'],
            ['name' => 'لتر',      'abbreviation' => 'ل'],
            ['name' => 'متر مكعب', 'abbreviation' => 'م³'],
            ['name' => 'قطعة',     'abbreviation' => 'ق'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
