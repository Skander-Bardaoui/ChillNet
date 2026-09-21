<x-back-layout :title="'Nouvelle résidence'">
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form method="POST" action="{{ route('back.residences.store') }}">
            @include('back.residences._form')
        </form>
    </div>
</x-back-layout>
