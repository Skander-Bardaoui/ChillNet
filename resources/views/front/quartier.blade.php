<x-public-layout :title="$quartier->nom">
    <a href="{{ route('home') }}" class="text-sm text-cherry hover:underline">&larr; Retour à l'accueil</a>

    <div class="bg-white rounded-lg shadow p-6 mt-4">
        <h1 class="text-2xl font-bold text-gray-900">{{ $quartier->nom }}</h1>
        <p class="text-gray-500">{{ $quartier->ville }} — {{ $quartier->code_postal }}</p>
        @if ($quartier->description)
            <p class="mt-4 text-gray-700">{{ $quartier->description }}</p>
        @endif
    </div>

    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-4">Résidences du quartier</h2>
    @if ($quartier->residences->isEmpty())
        <p class="text-gray-500">Aucune résidence enregistrée dans ce quartier.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($quartier->residences as $residence)
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="font-semibold text-gray-900">{{ $residence->nom }}</p>
                    <p class="text-sm text-gray-500">{{ $residence->adresse }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $residence->nombre_logements }} logement(s)</p>
                    <div class="flex gap-2 mt-2">
                        @if ($residence->salle_climatisee)
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Salle climatisée</span>
                        @endif
                        @if ($residence->point_fraicheur)
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Point de fraîcheur</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-public-layout>
