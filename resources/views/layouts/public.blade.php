<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ChillNet') }} — {{ $title ?? 'Anticiper la canicule' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cream text-gray-900">
        <nav class="bg-cherry text-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <a href="{{ route('home') }}" class="font-bold text-xl tracking-tight">
                        🌡️ ChillNet
                    </a>

                    <div class="flex items-center space-x-4 text-sm font-medium">
                        <a href="{{ route('home') }}" class="hover:text-cream-200">Accueil</a>

                        @auth
                            <a href="{{ route('dashboard') }}" class="hover:text-cream-200">Mon espace</a>
                            @if (Auth::user()->isAdmin() || Auth::user()->isGestionnaire())
                                <a href="{{ route('back.dashboard') }}" class="hover:text-cream-200">Espace gestion</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="hover:text-cream-200">Déconnexion</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-cream-200">Connexion</a>
                            <a href="{{ route('register') }}" class="bg-cream text-cherry px-3 py-1.5 rounded-md hover:bg-cream-200">Inscription</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        @if (session('success'))
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
