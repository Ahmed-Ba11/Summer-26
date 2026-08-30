<?php

/**
 * مستخدم مؤقّت لفحص المتصفّح — يُنشأ قبل الفحص ويُمحى بعده.
 *
 *   php tests/browser/probe-user.php create
 *   php tests/browser/probe-user.php destroy
 *
 * لا يمسّ أي بيانات قائمة: يعمل على مستخدم بريده `assistant-probe@example.test`
 * وحده، ويحذفه هو وعملياته حذفاً نهائياً في النهاية. لا `migrate` ولا
 * `migrate:fresh` ولا أي أمر يُسقط جدولاً.
 */

declare(strict_types=1);

// الاستيرادات قبل الإقلاع لا بعده: `use` لا يسري إلا على ما يليه في الملف،
// فوضعها تحت `$app->make(Kernel::class)` يجعل الاسم يُقرأ `'Kernel'` حرفياً
// ويفشل الحلّ. (فعلها `pint` تلقائياً حين كانت الاستيرادات في الأسفل.)
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\RateLimiter;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const PROBE_EMAIL = 'assistant-probe@example.test';
const PROBE_PASSWORD = 'assistant-probe-password';

/** وصف خبيث متعمَّد — يثبت أن التعقيم يعمل على مسار Markdown (معيار 15). */
const XSS_DESCRIPTION = '<img src=x onerror="window.__xss=1">';

$action = $argv[1] ?? 'create';

$existing = User::where('email', PROBE_EMAIL)->first();

if ($existing !== null) {
    RateLimiter::clear("assistant:hour:{$existing->id}");
    RateLimiter::clear("assistant:day:{$existing->id}");
    $existing->expenses()->forceDelete();
    $existing->incomes()->forceDelete();
    $existing->forceDelete();
}

if ($action === 'destroy') {
    echo json_encode(['destroyed' => $existing?->id, 'users' => User::count()])."\n";

    exit;
}

$user = User::create([
    'name' => 'Assistant Probe',
    'email' => PROBE_EMAIL,
    'password' => PROBE_PASSWORD,
]);

// بدون هذا يحوّل EnsureOnboarded كل طلباته إلى /welcome
$user->forceFill([
    'onboarding_completed_at' => now(),
    'monthly_income' => 1_000_000,
    'salary_day' => 27,
])->save();

$today = now(config('ai.assistant.timezone'));
$food = $user->categories()->where('name', 'طعام')->firstOrFail();
$transport = $user->categories()->where('name', 'مواصلات')->firstOrFail();

$rows = [
    ['category_id' => $food->id, 'amount' => 4500, 'description' => 'قهوة', 'days' => 1],
    ['category_id' => $food->id, 'amount' => 12000, 'description' => 'غداء', 'days' => 2],
    ['category_id' => $transport->id, 'amount' => 3000, 'description' => 'بنزين', 'days' => 3],
    ['category_id' => $food->id, 'amount' => 8000, 'description' => XSS_DESCRIPTION, 'days' => 4],
];

foreach ($rows as $row) {
    $user->expenses()->create([
        'category_id' => $row['category_id'],
        'amount' => $row['amount'],
        'description' => $row['description'],
        'expense_date' => $today->copy()->subDays($row['days'])->toDateString(),
        'is_recurring' => false,
    ]);
}

echo json_encode([
    'id' => $user->id,
    'email' => PROBE_EMAIL,
    'password' => PROBE_PASSWORD,
    'expenses' => $user->expenses()->count(),
    'sum_riyals' => $user->expenses()->sum('amount') / 100,
], JSON_UNESCAPED_UNICODE)."\n";
