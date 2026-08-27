<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * المستخدم الذي لم يُنهِ الإعداد يُحوَّل إلى `/welcome` من أي مسار.
 *
 * لماذا تحويل قسري لا لافتة داخل اللوحة: اللوحة قبل الإعداد أصفار في كل
 * بطاقة — «المتبقي 0» و«الحد اليومي 0» — وهذه أسوأ صورة أولى ممكنة.
 * المستخدم ينسحب قبل أن يفهم قيمة التطبيق أصلاً.
 */
class EnsureOnboarded
{
    /** مسارات لازمة قبل اكتمال الإعداد أو خارجه تماماً. */
    private const ALLOWED = [
        'welcome',
        'setup', 'setup/*',
        'onboarding', 'onboarding/*',
        'login', 'register', 'logout',
        'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge',
        'email/*', 'user/*',
        'up', '.well-known/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->onboarding_completed_at !== null) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('welcome');
    }
}
