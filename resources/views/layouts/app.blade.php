<!DOCTYPE html>
<html class="light" lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ config('app.name', 'ChillNet') }} — {{ $title ?? 'Votre espace citoyen' }}</title>
@include('layouts.stitch-head')
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen">
<a href="#contenu" class="skip-link">Aller au contenu</a>
@include('layouts.stitch-nav')
<main id="contenu" tabindex="-1" class="w-full pt-16 min-h-screen">
<div class="w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-space-lg">
    @if (session('success'))
        <div role="status" class="mb-space-md rounded-xl bg-surface-container-low border border-primary-container/30 text-on-surface px-space-md py-space-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">check_circle</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-space-md rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm flex items-center gap-2">
            <span class="material-symbols-outlined">warning</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (isset($header))
        <header class="mb-space-lg">{{ $header }}</header>
    @endif
    {{ $slot }}
</div>
</main>
@include('layouts.stitch-footer')
</body>
</html>
