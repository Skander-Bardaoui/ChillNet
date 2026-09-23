<x-back-layout :title="'Nouvelle résidence'">
    <div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
        <form method="POST" action="{{ route('back.residences.store') }}">
            @include('back.residences._form')
        </form>
    </div>
</x-back-layout>
