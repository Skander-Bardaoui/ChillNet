<x-back-layout :title="'Tableau de bord'">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold text-gray-800">Quartiers</h2>
            <p class="text-sm text-gray-500 mt-1">Gérez les quartiers de la zone couverte.</p>
            <a href="{{ route('back.quartiers.index') }}" class="inline-block mt-4 text-cherry hover:underline text-sm">
                Voir les quartiers &rarr;
            </a>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold text-gray-800">Résidences</h2>
            <p class="text-sm text-gray-500 mt-1">Gérez les résidences et leurs points de fraîcheur.</p>
            <a href="{{ route('back.residences.index') }}" class="inline-block mt-4 text-cherry hover:underline text-sm">
                Voir les résidences &rarr;
            </a>
        </div>
    </div>
</x-back-layout>
