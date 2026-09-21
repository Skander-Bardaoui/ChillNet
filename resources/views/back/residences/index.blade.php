<x-back-layout :title="'Résidences'">
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">{{ count($residences) }} résidence(s)</p>
        <a href="{{ route('back.residences.create') }}" class="bg-cherry text-white px-4 py-2 rounded-md text-sm hover:bg-cherry-700">
            + Nouvelle résidence
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-cream">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quartier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Logements</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Équipements</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($residences as $residence)
                    <tr>
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-900">{{ $residence->getNom() }}</p>
                            <p class="text-xs text-gray-500">{{ $residence->getAdresse() }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $residence->getQuartier()->getNom() }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $residence->getNombreLogements() }}</td>
                        <td class="px-6 py-4 space-x-1">
                            @if ($residence->isSalleClimatisee())
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Salle clim.</span>
                            @endif
                            @if ($residence->isPointFraicheur())
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Pt fraîcheur</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                            <a href="{{ route('back.residences.edit', $residence->getId()) }}" class="text-cherry hover:underline text-sm">Modifier</a>
                            <form method="POST" action="{{ route('back.residences.destroy', $residence->getId()) }}" class="inline" onsubmit="return confirm('Supprimer cette résidence ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucune résidence pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-back-layout>
