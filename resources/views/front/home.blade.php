<x-public-layout :title="'Accueil'">
    <div class="bg-white rounded-lg shadow p-8 mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Anticipez la canicule dans votre quartier</h1>
        <p class="mt-2 text-gray-600 max-w-2xl mx-auto">
            ChillNet centralise les alertes météo, les coupures de courant et les points de fraîcheur
            accessibles près de chez vous.
        </p>
    </div>

    <h2 class="text-xl font-semibold text-gray-900 mb-4">Points de fraîcheur accessibles</h2>
    @if ($pointsFraicheur->isEmpty())
        <p class="text-gray-500 mb-8">Aucun point de fraîcheur enregistré pour le moment.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach ($pointsFraicheur as $residence)
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
                    <p class="font-semibold text-gray-900">{{ $residence->nom }}</p>
                    <p class="text-sm text-gray-500">{{ $residence->adresse }}</p>
                    <p class="text-sm text-blue-600 mt-1">{{ $residence->quartier->nom ?? '—' }}</p>
                    @if ($residence->salle_climatisee)
                        <span class="inline-block mt-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Salle climatisée</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="text-xl font-semibold text-gray-900 mb-4">Quartiers</h2>
    @if ($quartiers->isEmpty())
        <p class="text-gray-500">Aucun quartier enregistré pour le moment.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($quartiers as $quartier)
                <a href="{{ route('quartiers.show', $quartier->id) }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition">
                    <p class="font-semibold text-gray-900">{{ $quartier->nom }}</p>
                    <p class="text-sm text-gray-500">{{ $quartier->ville }} — {{ $quartier->code_postal }}</p>
                    <p class="text-sm text-cherry mt-1">{{ $quartier->residences_count }} résidence(s)</p>
                </a>
            @endforeach
        </div>
    @endif
</x-public-layout>
