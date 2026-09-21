<x-back-layout :title="'Modifier le quartier'">
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form method="POST" action="{{ route('back.quartiers.update', $quartier->getId()) }}">
            @method('PUT')
            @include('back.quartiers._form')
        </form>
    </div>
</x-back-layout>
