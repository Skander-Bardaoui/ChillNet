<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mon espace habitant
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p>Bonjour <strong>{{ auth()->user()->name }}</strong>, bienvenue sur ChillNet.</p>

                @if (auth()->user()->residence)
                    <p class="mt-2 text-gray-600">
                        Votre résidence : <strong>{{ auth()->user()->residence->nom }}</strong>
                        ({{ auth()->user()->residence->quartier->nom ?? '—' }})
                    </p>
                @else
                    <p class="mt-2 text-gray-600">
                        Vous n'avez pas encore renseigné de résidence.
                        <a href="{{ route('profile.edit') }}" class="text-cherry hover:underline">Complétez votre profil</a>.
                    </p>
                @endif
            </div>

            <a href="{{ route('home') }}" class="inline-block text-cherry hover:underline">
                Voir les quartiers et points de fraîcheur &rarr;
            </a>
        </div>
    </div>
</x-app-layout>
