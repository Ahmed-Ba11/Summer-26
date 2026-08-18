<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * خريطة تحويل الإيموجي القديم إلى اسم أيقونة lucide.
     * نفس مصدر الحقيقة في `DashboardController::EMOJI_TO_ICON`.
     * أي قيمة غير معروفة تسقط على "ellipsis".
     */
    private const EMOJI_TO_ICON = [
        '🍔' => 'utensils', '🍕' => 'utensils', '☕' => 'coffee',
        '🚗' => 'car', '🚌' => 'bus', '⛽' => 'fuel',
        '🏠' => 'house', '⚡' => 'zap', '📶' => 'wifi', '📱' => 'phone',
        '💊' => 'pill', '🏥' => 'heart-pulse', '🏋' => 'dumbbell',
        '📚' => 'book-open', '🎓' => 'graduation-cap',
        '🎮' => 'gamepad-2', '✈' => 'plane', '🎁' => 'gift',
        '👕' => 'shirt', '💻' => 'laptop', '🐱' => 'cat',
        '💼' => 'briefcase', '💳' => 'credit-card', '💰' => 'banknote',
        '🔁' => 'repeat', '🛒' => 'shopping-cart', '📦' => 'ellipsis',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Category::query()
            ->whereNotNull('icon')
            ->get(['id', 'icon'])
            ->each(function (Category $c) {
                $icon = $c->icon;

                // قيمة lucide صحيحة أصلاً — لا تلمسها.
                if (preg_match('/^[a-z0-9-]+$/', $icon)) {
                    return;
                }

                foreach (self::EMOJI_TO_ICON as $emoji => $name) {
                    if (str_contains($icon, $emoji)) {
                        $c->icon = $name;
                        $c->save();

                        return;
                    }
                }

                $c->icon = 'ellipsis';
                $c->save();
            });
    }

    /**
     * Reverse the migrations.
     *
     * لا يمكن التراجع عن تحويل الإيموجي — القيمة الأصلية فُقدت.
     * الـ down غير ضروري لكن يُترك فارغاً لتجنب كسر أي أداة تراجع.
     */
    public function down(): void
    {
        // no-op: الإيموجي الأصلي لا يمكن استعادته.
    }
};
