<x-back-layout :title="'Modifier la résidence'">
    <div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
        <form method="POST" action="{{ route('back.residences.update', $residence->id) }}">
            @method('PUT')
            @include('back.residences._form')
        </form>
    </div>
</x-back-layout>
