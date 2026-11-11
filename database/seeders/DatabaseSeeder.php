<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed` — real store content everywhere; demo data only locally.
     * In production run: php artisan db:seed --class=StoreContentSeeder --force
     */
    public function run(): void
    {
        $this->call(StoreContentSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
