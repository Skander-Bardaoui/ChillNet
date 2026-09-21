<x-back-layout :title="'Modifier la résidence'">
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form method="POST" action="{{ route('back.residences.update', $residence->getId()) }}">
            @method('PUT')
            @include('back.residences._form')
        </form>
    </div>
</x-back-layout>
