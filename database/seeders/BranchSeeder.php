<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate(
            ['name' => 'Head Office'],
            [
                'address' => 'Jl. Utama No. 1',
                'phone'   => '08123456789',
            ]
        );
    }
}
