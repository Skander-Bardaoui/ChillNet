<x-back-layout :title="'Modifier le quartier'">
    <div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
        <form method="POST" action="{{ route('back.quartiers.update', $quartier->id) }}">
            @method('PUT')
            @include('back.quartiers._form')
        </form>
    </div>
</x-back-layout>
