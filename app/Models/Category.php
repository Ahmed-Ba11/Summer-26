<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
    ];

    /**
     * Default categories created for each user account.
     *
     * @return list<array{name: string, icon: string, color: string}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'طعام', 'icon' => 'utensils', 'color' => '#2a78d6'],
            ['name' => 'مواصلات', 'icon' => 'car', 'color' => '#eb6834'],
            ['name' => 'ترفيه', 'icon' => 'gamepad-2', 'color' => '#1baf7a'],
            ['name' => 'صحة', 'icon' => 'heart-pulse', 'color' => '#eda100'],
            ['name' => 'تعليم', 'icon' => 'graduation-cap', 'color' => '#e87ba4'],
            ['name' => 'تسوّق', 'icon' => 'shopping-cart', 'color' => '#008300'],
            ['name' => 'أخرى', 'icon' => 'ellipsis', 'color' => '#4a3aa7'],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
