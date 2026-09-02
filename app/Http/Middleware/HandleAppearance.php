<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * المظهر واللغة وحجم الخط تُشارَك مع القالب لتُرسم من الخادم مباشرة.
     * تفضيل المستخدم المسجَّل يسبق الكوكي — الكوكي لجهاز، والتفضيل لحساب.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $appearance = $user?->theme ?: ($request->cookie('appearance') ?? 'light');

        View::share('appearance', $appearance);
        View::share('uiLocale', $user?->locale ?: ($request->cookie('ui_locale') ?? 'ar'));
        View::share('fontScale', $user?->font_scale ?: ($request->cookie('font_scale') ?? 'md'));

        return $next($request);
    }
}