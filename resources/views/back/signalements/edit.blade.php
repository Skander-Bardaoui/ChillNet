<x-back-layout :title="'Modifier un signalement'">
    <section class="max-w-4xl rounded-2xl border border-outline-variant/20 bg-surface-container-low/80 p-space-md shadow-md md:p-space-lg">
        <form method="POST" action="{{ route('back.signalements.update', $signalement) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('back.signalements._form')
        </form>
    </section>
</x-back-layout>
