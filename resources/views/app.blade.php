<!DOCTYPE html>
{{-- الاتجاه وحجم الخط يُرسمان من الخادم: الضبط بـJS بعد التحميل يُنتج وميض
     تخطيط (قلب الصفحة أمام عين المستخدم) في كل زيارة أولى. --}}
<html
    lang="{{ $uiLocale ?? 'ar' }}"
    dir="{{ ($uiLocale ?? 'ar') === 'en' ? 'ltr' : 'rtl' }}"
    style="font-size: {{ ['sm' => '15px', 'md' => '16px', 'lg' => '17px'][$fontScale ?? 'md'] ?? '16px' }}"
    @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
