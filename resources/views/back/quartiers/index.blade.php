<x-back-layout :title="'Quartiers'">
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">{{ count($quartiers) }} quartier(s)</p>
        <a href="{{ route('back.quartiers.create') }}" class="bg-cherry text-white px-4 py-2 rounded-md text-sm hover:bg-cherry-700">
            + Nouveau quartier
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-cream">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ville</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code postal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Résidences</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($quartiers as $quartier)
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $quartier->getNom() }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $quartier->getVille() }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $quartier->getCodePostal() }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $quartier->getResidences()->count() }}</td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('back.quartiers.edit', $quartier->getId()) }}" class="text-cherry hover:underline text-sm">Modifier</a>
                            <form method="POST" action="{{ route('back.quartiers.destroy', $quartier->getId()) }}" class="inline" onsubmit="return confirm('Supprimer ce quartier ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucun quartier pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-back-layout>
