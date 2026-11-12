<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\User;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProdSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            PermissionsSeeder::class,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@campusflow.test',
            'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        ]);

        $admin->assignRole('admin');

        $year = Year::factory()->create([
            'name' => (string) now()->year,
        ]);

        Period::create([
            'name' => 'Default',
            'year_id' => $year->id,
            'start' => Carbon::now(),
            'end' => Carbon::now()->addMonth(),
        ]);
    }
}
