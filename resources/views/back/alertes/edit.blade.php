<x-back-layout :title="'Modifier l\'alerte'">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-lg max-w-6xl">
        <div class="lg:col-span-2 rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg">
            <form method="POST" action="{{ route('back.alertes.update', $alerte->id) }}">
                @include('back.alertes._form', ['alerte' => $alerte])
            </form>
        </div>
        <aside class="lg:col-span-1 lg:self-start">
            @include('back.alertes._meteo', ['meteo' => $meteo ?? null, 'meteoLieu' => $meteoLieu ?? null])
        </aside>
    </div>
</x-back-layout>
