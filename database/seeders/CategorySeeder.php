<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::query()->get() as $user) {
            foreach (Category::defaultDefinitions() as $default) {
                $user->categories()->firstOrCreate(
                    ['name' => $default['name']],
                    ['icon' => $default['icon'], 'color' => $default['color']],
                );
            }
        }
    }
}
