<x-back-layout :title="'Modifier un signalement'">
    <section class="max-w-4xl rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg flex flex-col gap-space-md">
        <div class="flex items-start gap-3">
            <div class="p-3 rounded-xl bg-primary-container/15 text-primary shrink-0"><span class="material-symbols-outlined text-[28px]">edit_note</span></div>
            <div>
                <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module signalements · Mise à jour</p>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Corrigez les informations du signalement. Toute évolution du statut notifie l'habitant.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('back.signalements.update', $signalement) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('back.signalements._form')
        </form>
    </section>
</x-back-layout>
