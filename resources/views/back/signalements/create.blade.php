<x-back-layout :title="'Nouveau signalement'">
    <section class="max-w-4xl rounded-2xl border border-outline-variant/20 bg-surface-container-low/80 p-space-md shadow-md md:p-space-lg">
        <p class="mb-5 text-sm text-on-surface-variant">Enregistrez un signalement communiqué à la gestion de la résidence. L’habitant recevra une notification.</p>
        <form method="POST" action="{{ route('back.signalements.store') }}" enctype="multipart/form-data">
            @include('back.signalements._form')
        </form>
    </section>
</x-back-layout>
