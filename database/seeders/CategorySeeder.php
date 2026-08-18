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

        // نفس قائمة DEFAULT_CATEGORIES في resources/js/lib/category-icons.ts.
        // الألوان hex من CATEGORY_PALETTE لأن العمود char(7) لا يسع متغير CSS.
        $defaults = [
            ['name' => 'طعام', 'icon' => 'utensils', 'color' => '#2a78d6'],
            ['name' => 'مواصلات', 'icon' => 'car', 'color' => '#eb6834'],
            ['name' => 'ترفيه', 'icon' => 'gamepad-2', 'color' => '#1baf7a'],
            ['name' => 'صحة', 'icon' => 'heart-pulse', 'color' => '#eda100'],
            ['name' => 'تعليم', 'icon' => 'graduation-cap', 'color' => '#e87ba4'],
            ['name' => 'تسوّق', 'icon' => 'shopping-cart', 'color' => '#008300'],
            ['name' => 'أخرى', 'icon' => 'ellipsis', 'color' => '#4a3aa7'],
        ];

        foreach ($defaults as $d) {
            Category::firstOrCreate(
                ['user_id' => $userId, 'name' => $d['name']],
                ['icon' => $d['icon'], 'color' => $d['color']]
            );
        }
    }
}
