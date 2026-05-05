<?php

namespace Database\Seeders;

use App\Models\EntryType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        EntryType::firstOrCreate(
            ['code' => 'client_payment'],
            [
                'name'        => 'تحصيل من عميل',
                'direction'   => 'in',
                'description' => 'مبلغ محصل من عميل مقابل طلب معين',
            ]
        );
    }
}
