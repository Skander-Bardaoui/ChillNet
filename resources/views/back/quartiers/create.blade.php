<x-back-layout :title="'Nouveau quartier'">
    <div class="bg-white rounded-lg shadow p-6 max-w-2xl">
        <form method="POST" action="{{ route('back.quartiers.store') }}">
            @include('back.quartiers._form')
        </form>
    </div>
</x-back-layout>
