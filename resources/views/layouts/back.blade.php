<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ChillNet') }} — Gestion {{ $title ? '· '.$title : '' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cream text-gray-900">
        <div class="flex min-h-screen">
            <aside class="w-64 bg-cherry-900 text-cream-200 flex-shrink-0">
                <div class="px-6 py-5 border-b border-cherry-700">
                    <a href="{{ route('back.dashboard') }}" class="font-bold text-lg text-white">🌡️ ChillNet · Gestion</a>
                </div>
                <nav class="px-3 py-4 space-y-1">
                    <a href="{{ route('back.dashboard') }}"
                       class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('back.dashboard') ? 'bg-cherry-700 text-white' : 'hover:bg-cherry-700/60' }}">
                        Tableau de bord
                    </a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('back.quartiers.index') }}"
                           class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('back.quartiers.*') ? 'bg-cherry-700 text-white' : 'hover:bg-cherry-700/60' }}">
                            Quartiers
                        </a>
                    @endif
                    <a href="{{ route('back.residences.index') }}"
                       class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs('back.residences.*') ? 'bg-cherry-700 text-white' : 'hover:bg-cherry-700/60' }}">
                        Résidences
                    </a>
                    <a href="{{ route('home') }}" class="block px-3 py-2 rounded-md text-sm hover:bg-cherry-700/60 mt-4 border-t border-cherry-700 pt-4">
                        &larr; Retour au site
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-sm hover:bg-cherry-700/60">
                            Déconnexion
                        </button>
                    </form>
                </nav>
            </aside>

            <div class="flex-1">
                <header class="bg-white shadow px-8 py-4 flex justify-between items-center">
                    <h1 class="text-lg font-semibold text-gray-800">{{ $title ?? 'Gestion' }}</h1>
                    <span class="text-sm text-gray-500">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                </header>

                <main class="p-8">
                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md">
                            {{ session('error') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
