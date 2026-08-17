<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::first()?->id ?? 1;

        $defaults = [
            ['name' => 'طعام', 'icon' => '🍔', 'color' => '#ef4444'],
            ['name' => 'مواصلات', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name' => 'ترفيه', 'icon' => '🎮', 'color' => '#eab308'],
            ['name' => 'فواتير', 'icon' => '⚡', 'color' => '#a855f7'],
            ['name' => 'صحة', 'icon' => '💊', 'color' => '#22c55e'],
            ['name' => 'تعليم', 'icon' => '📚', 'color' => '#6366f1'],
            ['name' => 'أخرى', 'icon' => '📦', 'color' => '#6b7280'],
        ];

        foreach ($defaults as $d) {
            Category::firstOrCreate(
                ['user_id' => $userId, 'name' => $d['name']],
                ['icon' => $d['icon'], 'color' => $d['color']]
            );
        }
    }
}
