<!DOCTYPE html>
<html class="light" lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ config('app.name', 'ChillNet') }}</title>
@include('layouts.stitch-head')
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen">
<a href="#contenu" class="skip-link">Aller au contenu</a>
@include('layouts.stitch-nav')
<main id="contenu" tabindex="-1" class="w-full pt-16 bg-surface min-h-screen">
<div class="w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-space-md flex flex-col gap-space-lg">
@isset($header)
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md">{{ $header }}</div>
@endisset
{{ $slot }}
</div>
</main>
@include('layouts.stitch-footer')
</body>
</html>
