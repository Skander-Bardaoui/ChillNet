<x-back-layout :title="'Nouveau signalement'">
    <section class="max-w-4xl rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg flex flex-col gap-space-md">
        <div class="flex items-start gap-3">
            <div class="p-3 rounded-xl bg-primary-container/15 text-primary shrink-0"><span class="material-symbols-outlined text-[28px]">report</span></div>
            <div>
                <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module signalements · Saisie gestionnaire</p>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Enregistrez un signalement au nom d'un habitant. Il recevra une notification à la création.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('back.signalements.store') }}" enctype="multipart/form-data">
            @include('back.signalements._form')
        </form>
    </section>
</x-back-layout>
